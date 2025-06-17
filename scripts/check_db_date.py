import mysql.connector

# MySQL接続情報
# .envファイルの情報に基づいて設定
DB_CONFIG = {
    'host': 'mysql',  # DB_HOST=mysql を使用
    'user': 'tiper',  # DB_USER=tiper を使用
    'password': '1492nabe', # DB_PASSWORD=1492nabe を使用
    'database': 'tiper' # DB_NAME=tiper を使用
}

def check_mysql_data():
    conn = None
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        cursor = conn.cursor()

        # raw_api_data テーブルの行数を確認
        cursor.execute("SELECT COUNT(*) FROM raw_api_data")
        raw_api_data_count = cursor.fetchone()[0]
        print(f"raw_api_data テーブルの総行数: {raw_api_data_count}")

        # products テーブルの行数を確認
        cursor.execute("SELECT COUNT(*) FROM products")
        products_count = cursor.fetchone()[0]
        print(f"products テーブルの総行数: {products_count}")

        # raw_api_data の最新の数件を表示 (row_json_dataは非常に大きいため表示しない)
        print("\nraw_api_data テーブルの最新の5件:")
        cursor.execute("SELECT id, fetched_at, updated_at FROM raw_api_data ORDER BY updated_at DESC LIMIT 5")
        latest_raw_data = cursor.fetchall()
        for row in latest_raw_data:
            print(f"  ID: {row[0]}, fetched_at: {row[1]}, updated_at: {row[2]}")

        # products の最新の数件を表示
        print("\nproducts テーブルの最新の5件:")
        cursor.execute("SELECT id, title, release_date, maker_name, genre, source_api FROM products ORDER BY updated_at DESC LIMIT 5")
        latest_products_data = cursor.fetchall()
        for row in latest_products_data:
            print(f"  ID: {row[0]}, Title: {row[1]}, Release Date: {row[2]}, Maker: {row[3]}, Genre: {row[4]}, Source API: {row[5]}")

    except mysql.connector.Error as err:
        print(f"MySQL接続エラー: {err}")
    finally:
        if conn and conn.is_connected():
            cursor.close()
            conn.close()
            print("MySQL接続を閉じました。")

if __name__ == "__main__":
    check_mysql_data()