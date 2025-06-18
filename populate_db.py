import os
import mysql.connector
import json
from datetime import datetime

# MySQL接続情報
DB_CONFIG = {
    'host': os.getenv('DB_HOST', 'localhost'), # Docker Composeのサービス名 'mysql' が渡される
    'user': os.getenv('DB_USER', 'root'),
    'password': os.getenv('DB_PASSWORD', 'your_db_password'), # ★修正: DB_PASS -> DB_PASSWORD に統一
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
        # 可能性のあるフォーマットを試す
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

def insert_raw_api_data(conn, data_json, api_name):
    """
    raw_api_data テーブルにAPIの生データを挿入する。
    既存のAPI名があれば更新、なければ新規挿入。
    """
    cursor = conn.cursor()
    now = datetime.now()
    data_json_str = json.dumps(data_json, ensure_ascii=False) # 日本語対応

    # api_name が 'item' の行を探す (ここでは source_api ではなく source_name を使う)
    # productsテーブルのsource_apiとraw_api_dataテーブルのsource_nameは意味的に対応
    cursor.execute("SELECT id FROM raw_api_data WHERE source_name = %s", (api_name,)) # ★修正: source_api -> source_name
    result = cursor.fetchone()

    if result:
        # 既存の行があれば更新
        raw_data_id = result[0]
        update_query = """
            UPDATE raw_api_data
            SET row_json_data = %s, updated_at = %s
            WHERE id = %s
        """
        cursor.execute(update_query, (data_json_str, now, raw_data_id))
        print(f"既存のraw_api_dataを更新しました (ID: {raw_data_id})。")
    else:
        # なければ新規挿入
        insert_query = """
            INSERT INTO raw_api_data (row_json_data, source_name, fetched_at, updated_at) # ★修正: source_api -> source_name
            VALUES (%s, %s, %s, %s)
        """
        cursor.execute(insert_query, (data_json_str, api_name, now, now))
        raw_data_id = cursor.lastrowid
        print(f"新しいraw_api_dataを挿入しました (ID: {raw_data_id})。")

    conn.commit()
    return raw_data_id

def populate_products_from_raw_data():
    """
    raw_api_data からデータを読み込み、products テーブルを更新または挿入する。
    """
    conn = None
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        cursor = conn.cursor()

        # productsテーブルにsource_apiカラムは存在しているので、このALTER TABLEは不要か、初回のみ
        # スクリプトの安定性のため、カラムが存在しない場合のみ追加するようにしておく
        try:
            cursor.execute("ALTER TABLE products ADD COLUMN IF NOT EXISTS source_api VARCHAR(50) NULL") # ★修正: VARCHAR(255) -> VARCHAR(50), IF NOT EXISTS 追加
            print("productsテーブルにsource_apiカラムを追加しました（もし存在しなければ）。")
        except mysql.connector.Error as err:
            # 1060 (ER_DUP_FIELDNAME) は出なくなるはずだが、念のため
            if err.errno == 1060:
                print("productsテーブルにsource_apiカラムは既に存在します。")
            else:
                print(f"productsテーブルへのカラム追加中にエラー: {err}") # 他のエラーは報告
                raise err # その他のエラーは再スロー

        # raw_api_data から最新の 'item' データを取得 (source_name で取得)
        cursor.execute("SELECT id, row_json_data, source_name FROM raw_api_data WHERE source_name = 'item' ORDER BY updated_at DESC LIMIT 1") # ★修正: raw_json_data -> row_json_data, source_name を取得
        raw_data_row = cursor.fetchone()

        if not raw_data_row:
            print("raw_api_data (source_name='item') が見つかりません。")
            # ダミーデータの挿入を促すメッセージ
            print("注意: APIからのデータ取得処理が未実装、またはデータが不足しています。")
            print("スクリプトの main ブロック内のダミーデータ挿入部分を参考に、raw_api_data テーブルにデータを挿入してください。")
            return

        raw_api_data_id = raw_data_row[0] # raw_api_data テーブルのID
        raw_json_data = json.loads(raw_data_row[1]) # row_json_data の内容
        source_api_value_from_raw_data = raw_data_row[2] # source_name の内容を source_api として使用

        # APIレスポンス構造の仮定: トップレベルに 'items' リストがある
        items = get_safe_value(raw_json_data, ['items'], [])

        if not items:
            print("APIデータに 'items' が見つからないか、空です。")
            return

        processed_count = 0
        for item_data in items:
            # ネストされたデータからの安全な値の取得
            product_id = clean_string(get_safe_value(item_data, ['item', 'content_id'])) # content_idも文字列として扱う
            title = clean_string(get_safe_value(item_data, ['item', 'title']))
            original_title = clean_string(get_safe_value(item_data, ['item', 'original_title'])) # original_titleを追加
            caption = clean_string(get_safe_value(item_data, ['item', 'caption'])) # captionを追加
            release_date = parse_date(get_safe_value(item_data, ['item', 'release_date']))
            maker_name = clean_string(get_safe_value(item_data, ['item', 'maker_name']))
            item_no = clean_string(get_safe_value(item_data, ['item', 'item_no'])) # item_noを追加
            price = convert_to_int(get_safe_value(item_data, ['item', 'price']), default=0) # priceを追加 (intに変換)
            volume = convert_to_int(get_safe_value(item_data, ['item', 'volume']), default=0) # volumeを追加 (intに変換)
            url = clean_string(get_safe_value(item_data, ['item', 'url'])) # urlを追加
            affiliate_url = clean_string(get_safe_value(item_data, ['item', 'affiliate_url'])) # affiliate_urlを追加
            image_url_small = clean_string(get_safe_value(item_data, ['item', 'image_url', 'small'])) # image_url_smallを追加
            image_url_medium = clean_string(get_safe_value(item_data, ['item', 'image_url', 'medium'])) # image_url_mediumを追加
            image_url_large = clean_string(get_safe_value(item_data, ['item', 'image_url', 'large'])) # image_url_largeを追加
            jacket_url_small = clean_string(get_safe_value(item_data, ['item', 'jacket_url', 'small'])) # jacket_url_smallを追加
            jacket_url_medium = clean_string(get_safe_value(item_data, ['item', 'jacket_url', 'medium'])) # jacket_url_mediumを追加
            jacket_url_large = clean_string(get_safe_value(item_data, ['item', 'jacket_url', 'large'])) # jacket_url_largeを追加
            sample_movie_url = clean_string(get_safe_value(item_data, ['item', 'sample_movie_url'])) # sample_movie_urlを追加
            sample_movie_capture_url = clean_string(get_safe_value(item_data, ['item', 'sample_movie_capture_url'])) # sample_movie_capture_urlを追加
            
            # productsテーブルの source_api には raw_api_data の source_name を利用
            source_api = source_api_value_from_raw_data

            if not product_id:
                print(f"警告: content_id が見つからないアイテムをスキップします: {item_data.get('item', {}).get('title', 'N/A')}")
                continue

            # データベースに製品が存在するか確認
            cursor.execute("SELECT id FROM products WHERE product_id = %s", (product_id,))
            existing_product = cursor.fetchone()

            now = datetime.now()

            if existing_product:
                # 既存の製品を更新
                update_query = """
                    UPDATE products
                    SET title = %s, original_title = %s, caption = %s, release_date = %s, maker_name = %s,
                        item_no = %s, price = %s, volume = %s, url = %s, affiliate_url = %s,
                        image_url_small = %s, image_url_medium = %s, image_url_large = %s,
                        jacket_url_small = %s, jacket_url_medium = %s, jacket_url_large = %s,
                        sample_movie_url = %s, sample_movie_capture_url = %s,
                        source_api = %s, raw_api_data_id = %s, updated_at = %s
                    WHERE product_id = %s
                """
                cursor.execute(update_query, (
                    title, original_title, caption, release_date, maker_name,
                    item_no, price, volume, url, affiliate_url,
                    image_url_small, image_url_medium, image_url_large,
                    jacket_url_small, jacket_url_medium, jacket_url_large,
                    sample_movie_url, sample_movie_capture_url,
                    source_api, raw_api_data_id, now, product_id
                ))
                print(f"製品を更新しました: ID={product_id}, Title='{title}'")
            else:
                # 新しい製品を挿入
                insert_query = """
                    INSERT INTO products (
                        product_id, title, original_title, caption, release_date, maker_name,
                        item_no, price, volume, url, affiliate_url,
                        image_url_small, image_url_medium, image_url_large,
                        jacket_url_small, jacket_url_medium, jacket_url_large,
                        sample_movie_url, sample_movie_capture_url,
                        source_api, raw_api_data_id, created_at, updated_at
                    ) VALUES (
                        %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s
                    )
                """
                cursor.execute(insert_query, (
                    product_id, title, original_title, caption, release_date, maker_name,
                    item_no, price, volume, url, affiliate_url,
                    image_url_small, image_url_medium, image_url_large,
                    jacket_url_small, jacket_url_medium, jacket_url_large,
                    sample_movie_url, sample_movie_capture_url,
                    source_api, raw_api_data_id, now, now
                ))
                print(f"新しい製品を挿入しました: ID={product_id}, Title='{title}'")
            processed_count += 1
        
        conn.commit()
        print(f"products テーブルへのデータ投入が完了しました。{processed_count} 件のアイテムを処理しました。")

    except mysql.connector.Error as err:
        print(f"MySQL接続またはクエリ実行エラー: {err}")
    except json.JSONDecodeError as err:
        print(f"JSONデコードエラー: {err} - データ: {raw_json_data[:100]}...") # エラー箇所のデータ一部表示
    except Exception as e:
        print(f"予期せぬエラーが発生しました: {e}")
    finally:
        if conn and conn.is_connected():
            cursor.close()
            conn.close()
            print("MySQL接続を閉じました。")

# ==============================================================================
# メイン処理
# ==============================================================================

if __name__ == "__main__":
    # ここでAPIからデータを取得し、生データテーブルに挿入する関数を呼び出す
    # 例: get_data_from_api_and_insert_raw_data()

    # ダミーデータ挿入の例 (もしAPI取得部分がまだ未実装の場合)
    # productsテーブルにデータを投入するためには、まずraw_api_dataテーブルにデータが必要です。
    # APIからデータを取得してraw_api_dataに挿入する処理をここで行うのが理想的です。
    # 以下のダミーデータを参考にして、raw_api_data にデータを入れてください。
    # 例:
    # dummy_api_data = {
    #     "items": [
    #         {
    #             "item": {
    #                 "content_id": "TEST001",
    #                 "title": "テストタイトル1",
    #                 "original_title": "Original Title 1",
    #                 "caption": "これはテスト用のキャプションです1。",
    #                 "release_date": "2023-01-01",
    #                 "maker_name": "テストメーカーA",
    #                 "item_no": "ABC-123",
    #                 "price": 1000,
    #                 "volume": 1,
    #                 "url": "http://example.com/test001",
    #                 "affiliate_url": "http://affiliate.example.com/test001",
    #                 "image_url": {
    #                     "small": "http://example.com/img/s/test001.jpg",
    #                     "medium": "http://example.com/img/m/test001.jpg",
    #                     "large": "http://example.com/img/l/test001.jpg"
    #                 },
    #                 "jacket_url": {
    #                     "small": "http://example.com/jacket/s/test001.jpg",
    #                     "medium": "http://example.com/jacket/m/test001.jpg",
    #                     "large": "http://example.com/jacket/l/test001.jpg"
    #                 },
    #                 "sample_movie_url": "http://example.com/mov/test001.mp4",
    #                 "sample_movie_capture_url": "http://example.com/mov_cap/test001.jpg",
    #                 "source_api": "test_api_source" # raw_api_data の source_name と対応
    #             }
    #         },
    #         {
    #             "item": {
    #                 "content_id": "TEST002",
    #                 "title": "テストタイトル2",
    #                 "original_title": "Original Title 2",
    #                 "caption": "これはテスト用のキャプションです2。",
    #                 "release_date": "2023-02-01",
    #                 "maker_name": "テストメーカーB",
    #                 "item_no": "XYZ-456",
    #                 "price": 2000,
    #                 "volume": 2,
    #                 "url": "http://example.com/test002",
    #                 "affiliate_url": "http://affiliate.example.com/test002",
    #                 "image_url": {
    #                     "small": "http://example.com/img/s/test002.jpg",
    #                     "medium": "http://example.com/img/m/test002.jpg",
    #                     "large": "http://example.com/img/l/test002.jpg"
    #                 },
    #                 "jacket_url": {
    #                     "small": "http://example.com/jacket/s/test002.jpg",
    #                     "medium": "http://example.com/jacket/m/test002.jpg",
    #                     "large": "http://example.com/jacket/l/test002.jpg"
    #                 },
    #                 "sample_movie_url": "http://example.com/mov/test002.mp4",
    #                 "sample_movie_capture_url": "http://example.com/mov_cap/test002.jpg",
    #                 "source_api": "test_api_source"
    #             }
    #         }
    #     ]
    # }
    # try:
    #     conn = mysql.connector.connect(**DB_CONFIG)
    #     insert_raw_api_data(conn, dummy_api_data, 'item') # 'item' は source_name の値として使用
    #     conn.close()
    #     print("ダミーデータをraw_api_dataテーブルに挿入しました。")
    # except Exception as e:
    #     print(f"ダミーデータ挿入エラー: {e}")


    # products テーブルへのデータ投入を実行
    populate_products_from_raw_data()