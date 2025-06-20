import os
import mysql.connector
import json
from datetime import datetime

# Dotenvライブラリを使って.envファイルをロード
from dotenv import load_dotenv
load_dotenv()

# MySQL接続情報
DB_CONFIG = {
    'host': os.getenv('DB_HOST', 'mysql'), 
    'user': os.getenv('DB_USER', 'root'),
    'password': os.getenv('DB_PASS', 'password'), 
    'database': os.getenv('DB_NAME', 'tiper')
}

# ==============================================================================
# ヘルパー関数群
# ==============================================================================

def get_safe_value(data, path, default=None):
    """
    ネストされたJSONデータから安全に値を取得する。
    例: get_safe_value(item_data, ['item', 'title'])
    例: get_safe_value(item_data, ['posterimage', 0, 'large'])
    """
    current_data = data
    for key in path:
        if isinstance(current_data, dict) and key in current_data:
            current_data = current_data[key]
        elif isinstance(current_data, list) and isinstance(key, int) and len(current_data) > key:
            current_data = current_data[key]
        else:
            return default
    return current_data

def parse_date(date_str):
    """日付文字列をYYYY-MM-DD形式に変換。無効な場合はNone。"""
    if not date_str:
        return None
    try:
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
    """
    sql = "SELECT id FROM categories WHERE type = %s AND name = %s"
    print(f"DEBUG SQL: get_or_create_category SELECT: SQL='{sql}', Params=(''{category_type}'', ''{category_name}'')") # デバッグログ
    cursor.execute(sql, (category_type, category_name))
    result = cursor.fetchone()
    if result:
        return result[0] 
    else:
        print(f"DEBUG: 新しいカテゴリを作成します: Type='{category_type}', Name='{category_name}'") # デバッグログ
        insert_sql = "INSERT INTO categories (type, name) VALUES (%s, %s)"
        print(f"DEBUG SQL: get_or_create_category INSERT: SQL='{insert_sql}', Params=(''{category_type}'', ''{category_name}'')") # デバッグログ
        try:
            cursor.execute(insert_sql, (category_type, category_name))
            # conn.commit() は削除。メインのトランザクションでまとめてコミット。
            return cursor.lastrowid
        except mysql.connector.Error as err:
            if err.errno == 1062: # Duplicate entry for key 'uk_type_name'
                print(f"DEBUG: カテゴリ '{category_type}' - '{category_name}' は既に存在するため、再取得します。")
                conn.rollback() # ロールバックして、再度SELECTを試みる
                print(f"DEBUG SQL: get_or_create_category SELECT (after rollback): SQL='{sql}', Params=(''{category_type}'', ''{category_name}'')") # デバッグログ
                cursor.execute(sql, (category_type, category_name))
                result_after_rollback = cursor.fetchone()
                if result_after_rollback:
                    return result_after_rollback[0]
                else:
                    # ここに到達することは稀だが、もし再取得も失敗したらエラー
                    print(f"ERROR: カテゴリ '{category_type}' - '{category_name}' の重複作成後の再取得に失敗しました。")
                    raise
            else:
                raise # その他のDBエラーは再スロー

def associate_product_with_category(cursor, conn, product_db_id: int, category_id: int):
    """
    product_idとcategory_idをproduct_categoriesテーブルに紐付ける。
    重複挿入を避ける。
    """
    sql = "SELECT id FROM product_categories WHERE product_id = %s AND category_id = %s"
    print(f"DEBUG SQL: associate_product_with_category SELECT: SQL='{sql}', Params=({product_db_id}, {category_id})") # デバッグログ
    cursor.execute(sql, (product_db_id, category_id))
    if not cursor.fetchone():
        insert_sql = "INSERT INTO product_categories (product_id, category_id) VALUES (%s, %s)"
        print(f"DEBUG SQL: associate_product_with_category INSERT: SQL='{insert_sql}', Params=({product_db_id}, {category_id})") # デバッグログ
        try:
            cursor.execute(insert_sql, (product_db_id, category_id))
            print(f"DEBUG: product_categories に紐付けを挿入: product_id={product_db_id}, category_id={category_id}") # デバッグログ
            # conn.commit() は populate_products_and_categories_main_loop の main commit でまとめて行う
        except mysql.connector.Error as err:
            if err.errno == 1062: # Duplicate entry for key 'uk_product_category'
                print(f"DEBUG: product_categories 紐付け (product_id={product_db_id}, category_id={category_id}) は既に存在するためスキップします。")
                # ロールバックは不要（ここでは個別のコミットはしないため）
            else:
                raise # その他のDBエラーは再スロー


def process_single_product_id_batch(cursor, conn, product_api_id: str, source_api_name: str):
    """
    特定の product_id (API側) と source_api に関連するraw_api_data全てを処理し、
    productsテーブルを更新、カテゴリを統合して紐付ける。
    """
    # 関連するraw_api_dataを全て取得 (processed_atがNULLのもの)
    select_raw_sql = """
        SELECT id, api_response_data, fetched_at
        FROM raw_api_data
        WHERE product_id = %s AND source_api = %s AND processed_at IS NULL
        ORDER BY fetched_at DESC, id DESC
    """
    print(f"DEBUG SQL: process_single_product_id_batch SELECT RAW: SQL='{select_raw_sql}', Params=(''{product_api_id}'', ''{source_api_name}'')") # デバッグログ
    cursor.execute(select_raw_sql, (product_api_id, source_api_name))
    all_raw_data_for_product = cursor.fetchall()

    if not all_raw_data_for_product:
        print(f"DEBUG: 処理すべきraw_api_dataが見つかりませんでした。Product ID: {product_api_id}, Source: {source_api_name}") # デバッグログ
        return # 処理すべきデータがなければ終了

    # productsテーブルを更新するための「メイン」となる生データを選択
    # ここでは最新の fetched_at を持つものをメインとする
    main_raw_data_row = all_raw_data_for_product[0]
    main_raw_api_data_id = main_raw_data_row[0]
    
    main_raw_json_data = json.loads(main_raw_data_row[1]) 
    main_item_data = main_raw_json_data 
    
    print(f"DEBUG: メインのitem_data (product_id: {product_api_id}): {main_item_data}") # デバッグログ

    # 全てのraw_api_dataレコードからカテゴリ情報を収集
    collected_genres = set()
    collected_actresses = set()
    collected_series = set()
    # maker_nameはproductsテーブルのメインデータとして取得し、categoriesにも追加する

    for raw_data_row in all_raw_data_for_product:
        current_raw_json_data = json.loads(raw_data_row[1])
        current_item_data = current_raw_json_data 
        print(f"DEBUG: カテゴリ収集元のitem_data: {current_item_data}") # デバッグログ

        # ジャンル収集 (Duga APIの 'category' -> 'data' に対応)
        category_wrapper = get_safe_value(current_item_data, ['category'])
        genres_data = get_safe_value(category_wrapper, ['data'], []) # category.data を取得

        print(f"DEBUG: 抽出された genres_data (from category.data): {genres_data} (タイプ: {type(genres_data)})")
        if isinstance(genres_data, list):
            for genre_entry in genres_data:
                if isinstance(genre_entry, dict) and 'name' in genre_entry:
                    genre_name = clean_string(genre_entry['name'])
                    if genre_name:
                        collected_genres.add(genre_name)

        # 女優収集 (Duga APIの 'performer' -> 'data' に対応)
        performer_wrapper = get_safe_value(current_item_data, ['performer'])
        actresses_data = get_safe_value(performer_wrapper, ['data'], []) # performer.data を取得

        print(f"DEBUG: 抽出された actresses_data (from performer.data): {actresses_data} (タイプ: {type(actresses_data)})")
        if isinstance(actresses_data, list):
            for actress_entry in actresses_data:
                if isinstance(actress_entry, dict) and 'name' in actress_entry:
                    actress_name = clean_string(actress_entry['name'])
                    if actress_name:
                        collected_actresses.add(actress_name)
        
        # シリーズ収集 (Duga APIの 'series' -> 'name' に対応)
        series_name_from_item = clean_string(get_safe_value(current_item_data, ['series', 'name']))
        if series_name_from_item:
            collected_series.add(series_name_from_item)
    
    print(f"DEBUG: 最終的に収集されたジャンル: {collected_genres}") # デバッグログ
    print(f"DEBUG: 最終的に収集された女優: {collected_actresses}") # デバッグログ
    print(f"DEBUG: 最終的に収集されたシリーズ: {collected_series}") # デバッグログ

    # productsテーブルに挿入するデータをメインの生データから抽出
    title = clean_string(get_safe_value(main_item_data, ['title']))
    print(f"DEBUG: 抽出されたタイトル (product_id: {product_api_id}): '{title}'") # デバッグログ
    # 「タイトルなし」をデフォルト値として使用
    if not title:
        title = "タイトルなし"

    original_title = clean_string(get_safe_value(main_item_data, ['originaltitle']))
    caption = clean_string(get_safe_value(main_item_data, ['caption']))
    
    # release_date を優先し、なければ opendate を使用
    release_date_str = clean_string(get_safe_value(main_item_data, ['releasedate']))
    if not release_date_str:
        release_date_str = clean_string(get_safe_value(main_item_data, ['opendate']))
    release_date = parse_date(release_date_str)
    
    maker_name = clean_string(get_safe_value(main_item_data, ['makername']))
    item_no = clean_string(get_safe_value(main_item_data, ['itemno'])) 
    
    price = float(str(get_safe_value(main_item_data, ['price'], default='0')).replace('円', '').replace(',', '').replace('～', '')) # 円とカンマ、「～」を除去してfloatに変換
    volume = convert_to_int(get_safe_value(main_item_data, ['volume']), default=0)
    url = clean_string(get_safe_value(main_item_data, ['url']))
    affiliate_url = clean_string(get_safe_value(main_item_data, ['affiliateurl'])) 
    
    # Duga APIのJSON構造に合わせた画像URLの抽出
    # productsテーブルのスキーマに合わせる
    image_url_small = clean_string(get_safe_value(main_item_data, ['posterimage', 0, 'small']))
    image_url_medium = clean_string(get_safe_value(main_item_data, ['posterimage', 0, 'midium']))
    image_url_large = clean_string(get_safe_value(main_item_data, ['posterimage', 0, 'large']))

    jacket_url_small = clean_string(get_safe_value(main_item_data, ['jacketimage', 0, 'small']))
    jacket_url_medium = clean_string(get_safe_value(main_item_data, ['jacketimage', 0, 'midium']))
    jacket_url_large = clean_string(get_safe_value(main_item_data, ['jacketimage', 0, 'large']))

    sample_movie_url = clean_string(get_safe_value(main_item_data, ['samplemovie', 0, 'midium', 'movie']))
    sample_movie_capture_url = clean_string(get_safe_value(main_item_data, ['samplemovie', 0, 'midium', 'capture']))

    # productsテーブルの genre (VARCHAR) フィールド用に、最初のジャンルを取得
    main_genre_str = None
    if collected_genres:
        main_genre_str = list(collected_genres)[0] # セットから最初の要素を取得

    source_api_for_products = source_api_name 
    
    if not product_api_id:
        print(f"警告: product_id (raw_api_data.product_id) が空のためスキップします (Source: {source_api_name}).")
        for raw_data_row in all_raw_data_for_product:
            update_raw_processed_sql = "UPDATE raw_api_data SET processed_at = %s WHERE id = %s"
            print(f"DEBUG SQL: Skipping product - UPDATE RAW PROCESSED: SQL='{update_raw_processed_sql}', Params=(''{datetime.now()}'', {raw_data_row[0]})") # デバッグログ
            cursor.execute(update_raw_processed_sql, (datetime.now(), raw_data_row[0]))
        return

    # データベースに製品が存在するか確認
    select_product_sql = "SELECT id FROM products WHERE product_id = %s"
    print(f"DEBUG SQL: SELECT PRODUCT: SQL='{select_product_sql}', Params=(''{product_api_id}'')") # デバッグログ
    cursor.execute(select_product_sql, (product_api_id,))
    existing_product = cursor.fetchone()

    now = datetime.now()
    product_db_id = None 

    if existing_product:
        product_db_id = existing_product[0]
        update_query = """
            UPDATE products
            SET title = %s, original_title = %s, caption = %s, release_date = %s, maker_name = %s,
                item_no = %s, price = %s, volume = %s, url = %s, affiliate_url = %s,
                image_url_small = %s, image_url_medium = %s, image_url_large = %s,
                jacket_url_small = %s, jacket_url_medium = %s, jacket_url_large = %s,
                sample_movie_url = %s, sample_movie_capture_url = %s,
                genre = %s, 
                source_api = %s, raw_api_data_id = %s, updated_at = %s
            WHERE product_id = %s
        """
        params = (
            title, original_title, caption, release_date, maker_name,
            item_no, price, volume, url, affiliate_url,
            image_url_small, image_url_medium, image_url_large,
            jacket_url_small, jacket_url_medium, jacket_url_large,
            sample_movie_url, sample_movie_capture_url,
            main_genre_str, 
            source_api_for_products, main_raw_api_data_id, now, product_api_id
        )
        print(f"DEBUG SQL: UPDATE PRODUCT: SQL='{update_query}', Params={params}") # デバッグログ
        cursor.execute(update_query, params)
        print(f"製品を更新しました: Product ID={product_api_id}, Title='{title}'")
    else:
        insert_query = """
            INSERT INTO products (
                product_id, title, original_title, caption, release_date, maker_name,
                item_no, price, volume, url, affiliate_url,
                image_url_small, image_url_medium, image_url_large,
                jacket_url_small, jacket_url_medium, jacket_url_large,
                sample_movie_url, sample_movie_capture_url,
                genre, 
                source_api, raw_api_data_id, created_at, updated_at
            ) VALUES (
                %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s
            )
        """
        params = (
            product_api_id, title, original_title, caption, release_date, maker_name,
            item_no, price, volume, url, affiliate_url,
            image_url_small, image_url_medium, image_url_large,
            jacket_url_small, jacket_url_medium, jacket_url_large,
            sample_movie_url, sample_movie_capture_url,
            main_genre_str, 
            source_api_for_products, main_raw_api_data_id, now, now
        )
        print(f"DEBUG SQL: INSERT PRODUCT: SQL='{insert_query}', Params={params}") # デバッグログ
        cursor.execute(insert_query, params)
        product_db_id = cursor.lastrowid 
        print(f"新しい製品を挿入しました: Product ID={product_api_id}, Title='{title}'")

    # カテゴリの分類と紐付け (products.id が確定した後に行う)
    if product_db_id:
        # 収集したジャンルを紐付け
        if collected_genres: 
            print(f"DEBUG: ジャンルを categories/product_categories に紐付けます。収集済み: {collected_genres}") 
        for genre_name in collected_genres:
            category_id = get_or_create_category(cursor, conn, "ジャンル", genre_name)
            associate_product_with_category(cursor, conn, product_db_id, category_id)
            print(f"  製品ID {product_api_id} にジャンル '{genre_name}' を紐付けました。")

        # 収集した女優を紐付け
        if collected_actresses: 
            print(f"DEBUG: 女優を categories/product_categories に紐付けます。収集済み: {collected_actresses}")
        for actress_name in collected_actresses:
            category_id = get_or_create_category(cursor, conn, "女優", actress_name)
            associate_product_with_category(cursor, conn, product_db_id, category_id)
            print(f"  製品ID {product_api_id} に女優 '{actress_name}' を紐付けました。")

        # レーベル (maker_name) を紐付け (メインデータから取得)
        if maker_name: 
            print(f"DEBUG: レーベルを categories/product_categories に紐付けます。名前: '{maker_name}'") # デバッグログ
            category_id = get_or_create_category(cursor, conn, "レーベル", maker_name)
            associate_product_with_category(cursor, conn, product_db_id, category_id)
            print(f"  製品ID {product_api_id} にレーベル '{maker_name}' を紐付けました。")

        # 収集したシリーズを紐付け
        if collected_series: 
            print(f"DEBUG: シリーズを categories/product_categories に紐付けます。収集済み: {collected_series}")
        for series_name in collected_series:
            category_id = get_or_create_category(cursor, conn, "シリーズ", series_name)
            associate_product_with_category(cursor, conn, product_db_id, category_id)
            print(f"  製品ID {product_api_id} にシリーズ '{series_name}' を紐付けました。")
        
        # 処理済みのraw_api_dataレコードにマークを付ける
        for raw_data_row in all_raw_data_for_product:
            update_raw_processed_sql = "UPDATE raw_api_data SET processed_at = %s WHERE id = %s"
            print(f"DEBUG SQL: UPDATE RAW PROCESSED: SQL='{update_raw_processed_sql}', Params=(''{now}'', {raw_data_row[0]})") # デバッグログ
            cursor.execute(update_raw_processed_sql, (now, raw_data_row[0]))

    return 1 


def populate_products_and_categories_main_loop():
    """
    raw_api_data から未処理のユニークな product_id, source_api の組み合わせを取得し、
    それぞれを process_single_product_id_batch で処理するメインループ。
    """
    conn = None
    total_products_processed = 0
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        cursor = conn.cursor()

        # processed_atカラムが存在しない場合の対処 (初めて実行する場合など)
        try:
            cursor.execute("ALTER TABLE raw_api_data ADD COLUMN processed_at DATETIME NULL COMMENT 'products/categoriesへの処理完了日時'")
            conn.commit()
            print("raw_api_dataテーブルにprocessed_atカラムを追加しました。")
        except mysql.connector.Error as err:
            if "Duplicate column name 'processed_at'" not in str(err):
                print(f"processed_atカラム追加エラー: {err}")
                conn.rollback()
            else:
                print("processed_atカラムは既に存在します。")

        # 未処理のユニークな (product_id, source_api) の組み合わせを取得
        select_distinct_sql = """
            SELECT DISTINCT product_id, source_api
            FROM raw_api_data
            WHERE processed_at IS NULL
            LIMIT 100 
        """
        print(f"DEBUG SQL: SELECT DISTINCT PRODUCTS TO PROCESS: SQL='{select_distinct_sql}'") # デバッグログ
        cursor.execute(select_distinct_sql)
        unique_product_ids_to_process = cursor.fetchall()

        if not unique_product_ids_to_process:
            print("処理すべきユニークな製品データが見つかりませんでした。")
            return

        print(f"raw_api_dataから {len(unique_product_ids_to_process)} 件のユニークな製品IDを処理します。")

        for product_api_id, source_api_name in unique_product_ids_to_process:
            try:
                conn.start_transaction() 
                processed_this_product = process_single_product_id_batch(cursor, conn, product_api_id, source_api_name)
                if processed_this_product:
                    total_products_processed += processed_this_product
                conn.commit() 
                print(f"製品ID {product_api_id} の処理とコミットが完了しました。")
            except Exception as e:
                print(f"製品ID {product_api_id} の処理中にエラーが発生しました: {e}")
                if conn and conn.is_connected():
                    conn.rollback()
                    print("トランザクションをロールバックしました。")

        print(f"products および categories テーブルへのデータ投入が完了しました。総計 {total_products_processed} 件の製品を処理しました。")

    except mysql.connector.Error as err:
        print(f"MySQL接続またはクエリ実行エラー: {err}")
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
# メイン処理
# ==============================================================================

if __name__ == "__main__":
    conn_check = None
    try:
        conn_check = mysql.connector.connect(**DB_CONFIG)
        cursor_check = conn_check.cursor()
        
        try:
            cursor_check.execute("ALTER TABLE raw_api_data ADD COLUMN processed_at DATETIME NULL COMMENT 'products/categoriesへの処理完了日時'")
            conn_check.commit()
            print("raw_api_dataテーブルにprocessed_atカラムを追加しました。")
        except mysql.connector.Error as err:
            if "Duplicate column name 'processed_at'" not in str(err):
                print(f"processed_atカラム追加エラー: {err}")
                conn_check.rollback()
            else:
                print("processed_atカラムは既に存在します。")

        cursor_check.execute("SELECT COUNT(*) FROM raw_api_data WHERE source_api = 'duga' AND processed_at IS NULL") 
        if cursor_check.fetchone()[0] == 0:
            print("raw_api_dataテーブルに処理すべき'duga'データがありません。ダミーデータを挿入します。(これはPHPスクリプトが動くまでのテスト用です)")
            # product_id が同じで、異なるカテゴリを持つダミーデータを複数挿入
            dummy_api_data_1_raw = {
                "item": {
                    "productid": "TEST_AGG_001",
                    "title": "集約テストタイトル",
                    "originaltitle": "Original Title Agg 1",
                    "caption": "集約テスト用のキャプションです1。",
                    "releasedate": "2024-01-01",
                    "opendate": "2024-01-05",
                    "makername": "集約メーカーX",
                    "itemno": "AGG-001",
                    "price": "1000円",
                    "volume": 60,
                    "url": "http://example.com/test/1",
                    "affiliateurl": "http://affiliate.example.com/test/1",
                    "posterimage": [{"small": "http://example.com/p_s1.jpg", "midium": "http://example.com/p_m1.jpg", "large": "http://example.com/p_l1.jpg"}],
                    "jacketimage": [{"small": "http://example.com/j_s1.jpg", "midium": "http://example.com/j_m1.jpg", "large": "http://example.com/j_l1.jpg"}],
                    "thumbnail": [{"image": "http://example.com/t_1.jpg"}, {"image": "http://example.com/t_2.jpg"}],
                    "samplemovie": [{"midium": {"movie": "http://example.com/mov1.mp4", "capture": "http://example.com/cap1.jpg"}}],
                    "category": {"data": [{"id": "GEN01", "name": "ジャンルA"}]},
                    "performer": {"data": [{"id": "ACT01", "name": "女優X"}]},
                    "series": {"name": "シリーズS1"},
                    "label": {"id": "LBL01", "name": "レーベルX"}
                }
            }
            dummy_api_data_2_raw = { # 同じproductidだが、カテゴリが異なる
                "item": {
                    "productid": "TEST_AGG_001", 
                    "title": "集約テストタイトル",
                    "originaltitle": "Original Title Agg 1b",
                    "caption": "集約テスト用のキャプションです1b。",
                    "releasedate": "2024-01-02", # 日付を少しずらす
                    "opendate": "2024-01-06",
                    "makername": "集約メーカーX",
                    "itemno": "AGG-001",
                    "price": "1000円",
                    "volume": 65,
                    "url": "http://example.com/test/1b",
                    "affiliateurl": "http://affiliate.example.com/test/1b",
                    "posterimage": [{"small": "http://example.com/p_s1b.jpg", "midium": "http://example.com/p_m1b.jpg", "large": "http://example.com/p_l1b.jpg"}],
                    "jacketimage": [{"small": "http://example.com/j_s1b.jpg", "midium": "http://example.com/j_m1b.jpg", "large": "http://example.com/j_l1b.jpg"}],
                    "thumbnail": [{"image": "http://example.com/t_1b.jpg"}, {"image": "http://example.com/t_2b.jpg"}],
                    "samplemovie": [{"midium": {"movie": "http://example.com/mov1b.mp4", "capture": "http://example.com/cap1b.jpg"}}],
                    "category": {"data": [{"id": "GEN02", "name": "ジャンルB"}]}, # 異なるジャンル
                    "performer": {"data": [{"id": "ACT02", "name": "女優Y"}]},    # 異なる女優
                    "series": {"name": "シリーズS2"},                                # 異なるシリーズ
                    "label": {"id": "LBL01", "name": "レーベルX"}
                }
            }
            dummy_api_data_3_raw = {
                "item": {
                    "productid": "TEST_AGG_002",
                    "title": "個別テストタイトル",
                    "originaltitle": "Original Title Ind 1",
                    "caption": "個別テスト用のキャプションです。",
                    "releasedate": "2024-03-01",
                    "opendate": "2024-03-05",
                    "makername": "個別メーカーY",
                    "itemno": "IND-001",
                    "price": "2000円",
                    "volume": 120,
                    "url": "http://example.com/test/2",
                    "affiliateurl": "http://affiliate.example.com/test/2",
                    "posterimage": [{"small": "http://example.com/p_s2.jpg", "midium": "http://example.com/p_m2.jpg", "large": "http://example.com/p_l2.jpg"}],
                    "jacketimage": [{"small": "http://example.com/j_s2.jpg", "midium": "http://example.com/j_m2.jpg", "large": "http://example.com/j_l2.jpg"}],
                    "thumbnail": [{"image": "http://example.com/t_3.jpg"}],
                    "samplemovie": [{"midium": {"movie": "http://example.com/mov2.mp4", "capture": "http://example.com/cap2.jpg"}}],
                    "category": {"data": [{"id": "GEN03", "name": "ジャンルC"}]},
                    "performer": {"data": [{"id": "ACT03", "name": "女優Z"}]},
                    "series": {"name": "シリーズS3"},
                    "label": {"id": "LBL02", "name": "レーベルY"}
                }
            }
            # priceが数値のみのケース (Pythonの price 変換ロジックテスト用)
            dummy_api_data_4_raw = {
                "item": {
                    "productid": "TEST_AGG_003",
                    "title": "価格数値テスト",
                    "releasedate": "2024-04-01",
                    "makername": "テストメーカーM",
                    "price": 1500, # 価格が数値で提供される場合
                    "url": "http://example.com/test/3",
                    "category": {"data": [{"name": "テストジャンル"}]},
                    "performer": {"data": [{"name": "テスト女優"}]}
                }
            }
            # ジャンル、女優が空、または存在しないケースのテスト
            dummy_api_data_5_raw = {
                "item": {
                    "productid": "TEST_AGG_004",
                    "title": "カテゴリなしテスト",
                    "releasedate": "2024-05-01",
                    "makername": "ノーカテゴリ",
                    "price": "500円",
                    "url": "http://example.com/test/4"
                    # category, performer, series, label は意図的に含めない
                }
            }

            insert_raw_api_data_dummy(conn_check, dummy_api_data_1_raw, 'duga', 'TEST_AGG_001')
            insert_raw_api_data_dummy(conn_check, dummy_api_data_2_raw, 'duga', 'TEST_AGG_001')
            insert_raw_api_data_dummy(conn_check, dummy_api_data_3_raw, 'duga', 'TEST_AGG_002')
            insert_raw_api_data_dummy(conn_check, dummy_api_data_4_raw, 'duga', 'TEST_AGG_003')
            insert_raw_api_data_dummy(conn_check, dummy_api_data_5_raw, 'duga', 'TEST_AGG_004')
            print("カテゴリ集約テスト用のダミーデータをraw_api_dataテーブルに挿入しました。")
        else:
            print("raw_api_dataテーブルに処理すべき'duga'データが既に存在します。")
    except Exception as e:
        print(f"ダミーデータ挿入チェックまたは挿入エラー: {e}")
    finally:
        if conn_check and conn_check.is_connected():
            cursor_check.close()
            conn_check.close()
            print("MySQL接続を閉じました。(ダミーデータチェック)")

    # products および categories テーブルへのデータ投入を実行
    populate_products_and_categories_main_loop()

# insert_raw_api_data_dummy 関数は main ブロックでのみ使用される仮の関数です
def insert_raw_api_data_dummy(conn, item_json_data_wrapper, source_api_name, product_id_val):
    cursor = conn.cursor()
    now = datetime.now()
    # PHPスクリプトが保存する形式 (itemの内容を直接JSON文字列として) に合わせる
    # item_json_data_wrapper は {"item": {...}} 形式を想定
    data_json_str = json.dumps(item_json_data_wrapper['item'], ensure_ascii=False) # 'item' キーの中身を直接保存

    insert_query = """
        INSERT INTO raw_api_data (product_id, api_response_data, source_api, fetched_at, updated_at, processed_at)
        VALUES (%s, %s, %s, %s, %s, NULL) 
    """
    print(f"DEBUG SQL: INSERT RAW DUMMY: SQL='{insert_query}', Params=(''{product_id_val}'', ''{data_json_str[:100]}...'', ''{source_api_name}'', ''{now}'', ''{now}'')") # デバッグログ (長すぎるとログが見にくいので短縮)
    cursor.execute(insert_query, (product_id_val, data_json_str, source_api_name, now, now))
    conn.commit()
    print(f"raw_api_data (ダミー) を挿入しました (product_id: {product_id_val}, source_api: {source_api_name})。")

