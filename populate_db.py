import os
import mysql.connector
import json
from datetime import datetime

# MySQL接続情報
DB_CONFIG = {
    'host': os.getenv('DB_HOST', 'localhost'), # Docker Composeのサービス名 'mysql' が渡される
    'user': os.getenv('DB_USER', 'root'),
    'password': os.getenv('DB_PASSWORD', 'your_db_password'), # DB_PASSWORD に統一
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

    # api_name が 'item' の行を探す
    cursor.execute("SELECT id FROM raw_api_data WHERE source_api = %s", (api_name,))
    result = cursor.fetchone()

    if result:
        # 既存の行があれば更新
        raw_data_id = result[0]
        update_query = """
            UPDATE raw_api_data
            SET row_json_data = %s, updated_at = %s  # ★修正: raw_json_data -> row_json_data
            WHERE id = %s
        """
        cursor.execute(update_query, (data_json_str, now, raw_data_id))
        print(f"既存のraw_api_dataを更新しました (ID: {raw_data_id})。")
    else:
        # なければ新規挿入
        insert_query = """
            INSERT INTO raw_api_data (row_json_data, source_api, fetched_at, updated_at) # ★修正: raw_json_data -> row_json_data
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

        # productsテーブルにsource_apiカラムがなければ追加
        try:
            cursor.execute("ALTER TABLE products ADD COLUMN source_api VARCHAR(255) NULL")
            print("productsテーブルにsource_apiカラムを追加しました。")
        except mysql.connector.Error as err:
            if err.errno == 1060: # ER_DUP_FIELDNAME (カラムが既に存在する)
                print("productsテーブルにsource_apiカラムは既に存在します。")
            else:
                raise err # その他のエラーは再スロー

        # raw_api_data から最新の 'item' データを取得
        cursor.execute("SELECT row_json_data FROM raw_api_data WHERE source_api = 'item' ORDER BY updated_at DESC LIMIT 1") # ★修正: raw_json_data -> row_json_data
        raw_data_row = cursor.fetchone()

        if not raw_data_row:
            print("raw_api_data (source_api='item') が見つかりません。")
            return

        raw_json_data = json.loads(raw_data_row[0]) # この変数名は raw_json_data のままでOK
        # APIレスポンス構造の仮定: トップレベルに 'items' リストがある
        items = get_safe_value(raw_json_data, ['items'], [])

        if not items:
            print("APIデータに 'items' が見つからないか、空です。")
            return

        processed_count = 0
        for item_data in items:
            # ネストされたデータからの安全な値の取得
            product_id = get_safe_value(item_data, ['item', 'content_id'])
            title = clean_string(get_safe_value(item_data, ['item', 'title']))
            release_date = parse_date(get_safe_value(item_data, ['item', 'release_date']))
            maker_name = clean_string(get_safe_value(item_data, ['item', 'maker_name']))
            genre = clean_string(get_safe_value(item_data, ['item', 'genre']))
            source_api = clean_string(get_safe_value(item_data, ['item', 'source_api'])) # 適切なAPI名を設定

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
                    SET title = %s, release_date = %s, maker_name = %s, genre = %s,
                        source_api = %s, updated_at = %s
                    WHERE product_id = %s
                """
                cursor.execute(update_query, (title, release_date, maker_name, genre, source_api, now, product_id))
                print(f"製品を更新しました: ID={product_id}, Title='{title}'")
            else:
                # 新しい製品を挿入
                insert_query = """
                    INSERT INTO products (product_id, title, release_date, maker_name, genre, source_api, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
                """
                cursor.execute(insert_query, (product_id, title, release_date, maker_name, genre, source_api, now, now))
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
    # 実際にはAPIからデータを取得し、raw_api_dataに挿入する処理をここで行います
    # 例えば、以下の行はAPIデータを取得して生のJSONとして保存するダミーの例です
    # 実際のAPIリクエストはあなたのアプリケーションの要件に合わせて実装してください
    # 例:
    # import requests
    # api_url = os.getenv('DUGA_API_URL', 'http://api.example.com/items')
    # api_key = os.getenv('DUGA_API_KEY', 'your_default_api_key')
    # headers = {'Authorization': f'Bearer {api_key}'}
    # try:
    #     response = requests.get(api_url, headers=headers)
    #     response.raise_for_status() # HTTPエラーが発生した場合に例外を発生させる
    #     api_data = response.json()
    #     conn = mysql.connector.connect(**DB_CONFIG)
    #     insert_raw_api_data(conn, api_data, 'item') # 'item' はAPIのソース名
    #     conn.close()
    # except Exception as e:
    #     print(f"APIデータ取得またはraw_api_data挿入エラー: {e}")

    # products テーブルへのデータ投入を実行
    populate_products_from_raw_data()