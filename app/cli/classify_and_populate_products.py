import os
import mysql.connector
import json
from datetime import datetime

# Dotenvライブラリを使って.envファイルをロード
# (このスクリプトが単独で実行される際に環境変数を読み込むため)
from dotenv import load_dotenv
load_dotenv()

# MySQL接続情報
# Docker Compose 環境では 'mysql' がサービス名としてホストになります
DB_CONFIG = {
    'host': os.getenv('DB_HOST', 'mysql'),
    'user': os.getenv('DB_USER', 'root'),
    'password': os.getenv('DB_PASS', 'password'), # .env の DB_PASS に合わせる
    'database': os.getenv('DB_NAME', 'tiper')
}

# ==============================================================================
# ヘルパー関数群
# = JSONデータからの安全な値の取得や、型変換など
# ==============================================================================

def get_safe_value(data, path, default=None):
    """
    ネストされたJSONデータから安全に値を取得する。
    例: get_safe_value(item_data, ['item', 'title'])
    """
    current_data = data
    for key in path:
        if isinstance(current_data, dict) and key in current_data:
            current_data = current_data[key]
        else:
            return default
    return current_data

def parse_date(date_str):
    """日付文字列をYYYY-MM-DD形式に変換。無効な場合はNone。"""
    if not date_str:
        return None
    try:
        # 可能性のあるフォーマットを試す (例: %Y-%m-%d, %Y/%m/%d, %Y%m%d)
        for fmt in ("%Y-%m-%d", "%Y/%m/%d", "%Y%m%d"):
            return datetime.strptime(date_str, fmt).strftime("%Y-%m-%d")
    except ValueError:
        pass
    return None

def convert_to_int(value, default=0):
    """値を整数に変換。変換できない場合はデフォルト値を返す。"""
    try:
        return int(value)
    except (ValueError, TypeError):
        return default

def clean_string(value):
    """文字列をクリーンアップし、Noneや空文字列をNoneに変換。"""
    if value is None:
        return None
    s = str(value).strip()
    return s if s else None

# ==============================================================================
# データベース操作関数
# ==============================================================================

def get_or_create_category(cursor, conn, category_type: str, category_name: str) -> int:
    """
    カテゴリが存在すればそのIDを返し、なければ新規作成してIDを返す。
    categoriesテーブルの (type, name) はUNIQUE制約を持つべき。
    """
    sql = "SELECT id FROM categories WHERE type = %s AND name = %s"
    cursor.execute(sql, (category_type, category_name))
    result = cursor.fetchone()
    if result:
        return result[0] # タプルで返されるため、0番目の要素
    else:
        insert_sql = "INSERT INTO categories (type, name) VALUES (%s, %s)"
        cursor.execute(insert_sql, (category_type, category_name))
        conn.commit() # カテゴリの挿入は即座にコミット（カテゴリの衝突を減らすため）
        return cursor.lastrowid

def associate_product_with_category(cursor, conn, product_db_id: int, category_id: int):
    """
    product_idとcategory_idをproduct_categoriesテーブルに紐付ける。
    重複挿入を避ける (product_categoriesテーブルのUNIQUE KEYを利用)。
    """
    insert_sql = "INSERT IGNORE INTO product_categories (product_id, category_id) VALUES (%s, %s)"
    cursor.execute(insert_sql, (product_db_id, category_id))
    # ここではコミットせず、メイン処理のトランザクションでまとめてコミットする

def ensure_processed_at_column_exists(cursor, conn):
    """
    raw_api_dataテーブルにprocessed_atカラムが存在することを確認し、なければ追加する。
    """
    try:
        cursor.execute("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = 'raw_api_data' AND COLUMN_NAME = 'processed_at'", (DB_CONFIG['database'],))
        if cursor.fetchone() is None:
            print("raw_api_dataテーブルにprocessed_atカラムを追加します...")
            alter_sql = "ALTER TABLE `raw_api_data` ADD COLUMN `processed_at` DATETIME NULL COMMENT 'products/categoriesへの処理が完了した日時 (NULLの場合は未処理)'"
            cursor.execute(alter_sql)
            conn.commit()
            print("processed_atカラムが正常に追加されました。")
        else:
            print("processed_atカラムは既に存在します。")
    except mysql.connector.Error as err:
        # 重複カラムエラーは無視
        if "Duplicate column name 'processed_at'" not in str(err):
            print(f"processed_atカラムの確認または追加エラー: {err}")
            raise # その他のエラーは再スローする

def process_product_batch_from_raw_data(cursor, conn, product_api_id: str, source_api_name: str):
    """
    特定の product_id (API側) と source_api に関連するraw_api_data全てを処理し、
    productsテーブルを更新、カテゴリを統合して紐付ける。
    """
    # 関連するraw_api_dataを全て取得 (processed_atがNULLのもの)
    cursor.execute("""
        SELECT id, api_response_data, fetched_at
        FROM raw_api_data
        WHERE product_id = %s AND source_api = %s AND processed_at IS NULL
        ORDER BY fetched_at DESC, id DESC
    """, (product_api_id, source_api_name))
    all_raw_data_for_product = cursor.fetchall()

    if not all_raw_data_for_product:
        return 0 # 処理すべきデータがなければ0を返す

    # productsテーブルを更新するための「メイン」となる生データを選択
    # ここでは最新の fetched_at を持つものをメインとする
    # (fetched_at が同じ場合は id が大きい方を選ぶことで一意性を保つ)
    main_raw_data_row = all_raw_data_for_product[0]
    main_raw_api_data_id = main_raw_data_row[0]
    main_raw_json_data = json.loads(main_raw_data_row[1])
    
    main_item_data = main_raw_json_data.get('item', {})

    # 全てのraw_api_dataレコードからカテゴリ情報を収集
    collected_genres = set()
    collected_actresses = set()
    collected_series_names = set() # シリーズ名は文字列で収集
    
    # 画像URLの候補を収集 (優先順位: large -> medium -> small)
    main_image_candidates = []
    og_image_candidates = [] # OGP画像もメイン画像と同じロジックで収集

    for raw_data_row in all_raw_data_for_product:
        current_raw_json_data = json.loads(raw_data_row[1])
        current_item_data = current_raw_json_data.get('item', {})

        # ジャンル収集
        genres = get_safe_value(current_item_data, ['genres'], [])
        if isinstance(genres, dict) and 'genre' in genres: 
            genres = get_safe_value(genres, ['genre'], [])
        for genre_entry in genres:
            if isinstance(genre_entry, dict) and 'name' in genre_entry:
                genre_name = clean_string(genre_entry['name'])
                if genre_name:
                    collected_genres.add(genre_name)

        # 女優収集
        actresses = get_safe_value(current_item_data, ['actresses'], [])
        if isinstance(actresses, dict) and 'actress' in actresses: 
            actresses = get_safe_value(actresses, ['actress'], [])
        for actress_entry in actresses:
            if isinstance(actress_entry, dict) and 'name' in actress_entry:
                actress_name = clean_string(actress_entry['name'])
                if actress_name:
                    collected_actresses.add(actress_name)
        
        # シリーズ収集
        series_name = clean_string(get_safe_value(current_item_data, ['series', 'name']))
        if series_name:
            collected_series_names.add(series_name)

        # 画像URL候補の収集
        img_urls = get_safe_value(current_item_data, ['image_url'], {})
        jkt_urls = get_safe_value(current_item_data, ['jacket_url'], {})
        
        if get_safe_value(img_urls, ['large']): main_image_candidates.append(get_safe_value(img_urls, ['large']))
        if get_safe_value(jkt_urls, ['large']): main_image_candidates.append(get_safe_value(jkt_urls, ['large']))
        if get_safe_value(img_urls, ['medium']): main_image_candidates.append(get_safe_value(img_urls, ['medium']))
        if get_safe_value(jkt_urls, ['medium']): main_image_candidates.append(get_safe_value(jkt_urls, ['medium']))
        if get_safe_value(img_urls, ['small']): main_image_candidates.append(get_safe_value(img_urls, ['small']))
        if get_safe_value(jkt_urls, ['small']): main_image_candidates.append(get_safe_value(jkt_urls, ['small']))
        
        # OGP画像は、もし専用フィールドがあればそれを優先、なければメイン画像と同じロジック
        # Duga APIには専用のOGPフィールドがないため、メイン画像と同じ候補リストを使用
        og_image_candidates.extend(main_image_candidates) # 同じ候補リストを共有


    # productsテーブルに挿入するデータをメインの生データから抽出
    title = clean_string(get_safe_value(main_item_data, ['title']))
    original_title = clean_string(get_safe_value(main_item_data, ['original_title']))
    caption = clean_string(get_safe_value(main_item_data, ['caption']))
    release_date = parse_date(get_safe_value(main_item_data, ['release_date']))
    maker_name = clean_string(get_safe_value(main_item_data, ['maker_name']))
    item_no = clean_string(get_safe_value(main_item_data, ['item_no']))
    price = float(get_safe_value(main_item_data, ['price'], default=0.0))
    volume = convert_to_int(get_safe_value(main_item_data, ['volume']), default=0)
    url = clean_string(get_safe_value(main_item_data, ['url']))
    affiliate_url = clean_string(get_safe_value(main_item_data, ['affiliate_url']))
    sample_movie_url = clean_string(get_safe_value(main_item_data, ['sample_movie_url']))
    sample_movie_capture_url = clean_string(get_safe_value(main_item_data, ['sample_movie_capture_url']))
    source_api_for_products = source_api_name # productsテーブルの source_api には raw_api_data の source_api を利用
    
    # メイン画像URLの選定 (優先順位付け)
    main_image_url = None
    if main_image_candidates:
        # 重複を排除し、順番を保持するためにsetからlistに戻す
        main_image_candidates_unique = list(dict.fromkeys(main_image_candidates)) 
        main_image_url = clean_string(main_image_candidates_unique[0]) # 最初の有効なURLを採用

    # OGP画像URLの選定 (メイン画像がない場合はOGP画像もなし)
    og_image_url = None
    if og_image_candidates:
        og_image_candidates_unique = list(dict.fromkeys(og_image_candidates))
        og_image_url = clean_string(og_image_candidates_unique[0])


    if not product_api_id:
        print(f"警告: product_id (raw_api_data.product_id) が空のためスキップします (Source: {source_api_name}).")
        # 処理済みのraw_api_dataレコードにマークを付ける (スキップされたものも)
        for raw_data_row in all_raw_data_for_product:
            update_raw_processed_sql = "UPDATE raw_api_data SET processed_at = %s WHERE id = %s"
            cursor.execute(update_raw_processed_sql, (datetime.now(), raw_data_row[0]))
        return 0

    # ★修正点: タイトルが空の場合のスキップ処理を追加
    if not title: # titleがNoneまたは空文字列の場合
        print(f"警告: 製品タイトルが空のためスキップします。Product ID: {product_api_id} (Source: {source_api_name}).")
        # 関連するraw_api_dataレコードも処理済みとしてマーク
        for raw_data_row in all_raw_data_for_product:
            update_raw_processed_sql = "UPDATE raw_api_data SET processed_at = %s WHERE id = %s"
            cursor.execute(update_raw_processed_sql, (datetime.now(), raw_data_row[0]))
        return 0


    # データベースに製品が存在するか確認
    cursor.execute("SELECT id FROM products WHERE product_id = %s", (product_api_id,))
    existing_product = cursor.fetchone()

    now = datetime.now()
    product_db_id = None # productsテーブルのIDを初期化

    # JSONデータを文字列として準備
    genres_json_str = json.dumps(list(collected_genres), ensure_ascii=False) if collected_genres else None
    actresses_json_str = json.dumps(list(collected_actresses), ensure_ascii=False) if collected_actresses else None
    series_json_str = json.dumps(list(collected_series_names), ensure_ascii=False) if collected_series_names else None


    if existing_product:
        product_db_id = existing_product[0]
        # 既存の製品を更新
        update_query = """
            UPDATE products
            SET title = %s, original_title = %s, caption = %s, release_date = %s, maker_name = %s,
                item_no = %s, price = %s, volume = %s, url = %s, affiliate_url = %s,
                main_image_url = %s, og_image_url = %s, sample_movie_url = %s, sample_movie_capture_url = %s,
                actresses_json = %s, genres_json = %s, series_json = %s,
                source_api = %s, raw_api_data_id = %s
            WHERE product_id = %s
        """
        cursor.execute(update_query, (
            title, original_title, caption, release_date, maker_name,
            item_no, price, volume, url, affiliate_url,
            main_image_url, og_image_url, sample_movie_url, sample_movie_capture_url,
            actresses_json_str, genres_json_str, series_json_str, # JSONカラム
            source_api_for_products, main_raw_api_data_id, product_api_id 
        ))
        print(f"製品を更新しました: Product ID={product_api_id}, Title='{title}'")
    else:
        # 新しい製品を挿入
        # productsテーブルの created_at と updated_at は MySQL で自動生成されるため、INSERT文からは除外
        insert_query = """
            INSERT INTO products (
                product_id, title, original_title, caption, release_date, maker_name,
                item_no, price, volume, url, affiliate_url,
                main_image_url, og_image_url, sample_movie_url, sample_movie_capture_url,
                actresses_json, genres_json, series_json,
                source_api, raw_api_data_id
            ) VALUES (
                %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s
            )
        """
        cursor.execute(insert_query, (
            product_api_id, title, original_title, caption, release_date, maker_name,
            item_no, price, volume, url, affiliate_url,
            main_image_url, og_image_url, sample_movie_url, sample_movie_capture_url,
            actresses_json_str, genres_json_str, series_json_str, # JSONカラム
            source_api_for_products, main_raw_api_data_id
        ))
        product_db_id = cursor.lastrowid # 新規挿入されたproductsテーブルのIDを取得
        print(f"新しい製品を挿入しました: Product ID={product_api_id}, Title='{title}'")

    # categories および product_categories テーブルへの紐付け
    # (productsテーブルのJSONカラムに保存する情報とは別に、カテゴリ管理用のテーブルにも紐付ける)
    if product_db_id:
        # 収集したジャンルを紐付け
        for genre_name in collected_genres:
            category_id = get_or_create_category(cursor, conn, "ジャンル", genre_name)
            associate_product_with_category(cursor, conn, product_db_id, category_id)
            # print(f"  製品ID {product_api_id} にジャンル '{genre_name}' を紐付けました。") # 大量ログ防止のためコメントアウト

        # 収集した女優を紐付け
        for actress_name in collected_actresses:
            category_id = get_or_create_category(cursor, conn, "女優", actress_name)
            associate_product_with_category(cursor, conn, product_db_id, category_id)
            # print(f"  製品ID {product_api_id} に女優 '{actress_name}' を紐付けました。") # 大量ログ防止のためコメントアウト

        # レーベル (maker_name) を紐付け (メインデータから取得)
        if maker_name: 
            category_id = get_or_create_category(cursor, conn, "レーベル", maker_name)
            associate_product_with_category(cursor, conn, product_db_id, category_id)
            # print(f"  製品ID {product_api_id} にレーベル '{maker_name}' を紐付けました。") # 大量ログ防止のためコメントアウト

        # 収集したシリーズを紐付け
        for series_name in collected_series_names: # シリーズ名セットを使用
            category_id = get_or_create_category(cursor, conn, "シリーズ", series_name)
            associate_product_with_category(cursor, conn, product_db_id, category_id)
            # print(f"  製品ID {product_api_id} にシリーズ '{series_name}' を紐付けました。") # 大量ログ防止のためコメントアウト
        
        # 処理済みのraw_api_dataレコードにマークを付ける
        for raw_data_row in all_raw_data_for_product:
            update_raw_processed_sql = "UPDATE raw_api_data SET processed_at = %s WHERE id = %s"
            cursor.execute(update_raw_processed_sql, (datetime.now(), raw_data_row[0]))

    return 1 # 処理した製品数を返すため


def main_classification_process():
    """
    raw_api_data から未処理のユニークな product_id, source_api の組み合わせを取得し、
    それぞれを process_product_batch_from_raw_data で処理するメインループ。
    """
    conn = None
    total_products_processed = 0
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        cursor = conn.cursor()

        # raw_api_dataテーブルにprocessed_atカラムが存在することを確認し、なければ追加する
        ensure_processed_at_column_exists(cursor, conn)
        
        # 未処理のユニークな (product_id, source_api) の組み合わせを取得
        # LIMIT 100 は一度にメモリに読み込む重複したraw_api_dataレコードの数を制御するために重要
        cursor.execute("""
            SELECT DISTINCT product_id, source_api
            FROM raw_api_data
            WHERE processed_at IS NULL
            LIMIT 1000 -- 一度に処理するユニークな製品IDの数を増やしました
        """)
        unique_product_ids_to_process = cursor.fetchall()

        if not unique_product_ids_to_process:
            print("処理すべきユニークな製品データが見つかりませんでした。")
            return

        print(f"raw_api_dataから {len(unique_product_ids_to_process)} 件のユニークな製品IDを処理します。")

        # 各ユニークな製品IDについて処理を実行
        for product_api_id, source_api_name in unique_product_ids_to_process:
            try:
                conn.start_transaction() # 各製品IDの処理前にトランザクション開始
                processed_count_for_this_product = process_product_batch_from_raw_data(cursor, conn, product_api_id, source_api_name)
                total_products_processed += processed_count_for_this_product
                conn.commit() # 各製品IDの処理後にコミット
                print(f"製品ID {product_api_id} の処理とコミットが完了しました。")
            except Exception as e:
                print(f"製品ID {product_api_id} の処理中にエラーが発生しました: {e}")
                if conn and conn.is_connected():
                    conn.rollback()
                    print("トランザクションをロールバックしました。")
                # エラーが発生した product_id の raw_api_data は processed_at が更新されないため、次回の実行で再度試行される

        print(f"製品、カテゴリ、および紐付けテーブルへのデータ投入が完了しました。総計 {total_products_processed} 件の製品を処理しました。")

    except mysql.connector.Error as err:
        print(f"MySQL接続またはクエリ実行エラー: {err}")
        if conn and conn.is_connected():
            conn.rollback() 
            print("メインループ中にトランザクションをロールバックしました。")
    except json.JSONDecodeError as err:
        print(f"JSONデコードエラー: {err}")
        if conn and conn.is_connected():
            conn.rollback()
            print("メインループ中にトランザクションをロールバックしました。")
    except Exception as e:
        print(f"予期せぬエラーが発生しました: {e}")
        if conn and conn.is_connected():
            conn.rollback()
            print("メインループ中にトランザクションをロールバックしました。")
    finally:
        if conn and conn.is_connected():
            cursor.close()
            conn.close()
            print("MySQL接続を閉じました。")

# ==============================================================================
# メイン処理の実行
# ==============================================================================

if __name__ == "__main__":
    # ここにダミーデータ挿入のロジックは含めません。
    # raw_api_data にはPHPスクリプトでデータが投入されていることを前提とします。
    # もしテスト用にダミーデータが必要な場合は、別途関数を定義し呼び出すか、
    # PHPスクリプトを実行して raw_api_data にデータを投入してください。
    
    # 製品とカテゴリの分類・投入プロセスを実行
    main_classification_process()
