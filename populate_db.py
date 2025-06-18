DB_CONFIG = {
    'host': os.getenv('DB_HOST', 'localhost'), # Docker Composeのサービス名 'mysql' が渡される
    'user': os.getenv('DB_USER', 'root'),
    'password': os.getenv('DB_PASS', 'your_db_password'),
    'database': os.getenv('DB_NAME', 'tiper')
}

# ... その他の関数 (変更なし) ...

# メイン処理の関数 (変更なし)
def populate_products_from_raw_data():
    # ...maya@x162-43-71-24:~/my_web_project$