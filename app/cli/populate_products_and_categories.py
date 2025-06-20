import os
import mysql.connector
import json
from datetime import datetime
import sys
import logging # loggingモジュールを追加

# ==============================================================================
# ロギング設定
# ==============================================================================
# ロガーを初期化
logger = logging.getLogger(__name__)
logger.setLevel(logging.DEBUG) # 全てのデバッグレベルのログを出力

# コンソールハンドラー
console_handler = logging.StreamHandler(sys.stdout)
console_handler.setLevel(logging.DEBUG)
console_formatter = logging.Formatter('%(levelname)s: %(message)s')
console_handler.setFormatter(console_formatter)
logger.addHandler(console_handler)

# ファイルハンドラー
log_file_path = '/var/www/html/app/logs/populate_script.log'
# ディレクトリが存在しない場合に作成
os.makedirs(os.path.dirname(log_file_path), exist_ok=True)
file_handler = logging.FileHandler(log_file_path, mode='a', encoding='utf-8')
file_handler.setLevel(logging.DEBUG)
file_formatter = logging.Formatter('%(asctime)s - %(levelname)s - %(message)s')
file_handler.setFormatter(file_formatter)
logger.addHandler(file_handler)

logger.info("populate_products_and_categories.py スクリプト開始。")

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

def convert_to_float(value, default=0.0):
    """
    文字列から「円」「~」「,」を除去し、floatに変換する。
    変換できない場合はデフォルト値を返す。
    """
    if value is None:
        return default
    s = str(value).strip()
    # 日本円記号、波線、カンマを除去
    s = s.replace('円', '').replace('～', '').replace(',', '')
    try:
        return float(s)
    except (ValueError, TypeError):
        return default

# ==============================================================================
# データベース操作関数
# ==============================================================================

def get_or_create_category(cursor, conn, category_type: str, category_name: str) -> int:
    """
    カテゴリが存在すればそのIDを返し、なければ新規作成してIDを返す。
    この関数は外部から呼び出されるトランザクション内で実行されることを想定し、
    独自のコミットやロールバックは行いません。
    """
    sql = "SELECT id FROM categories WHERE type = %s AND name = %s"
    logger.debug(f"DEBUG SQL: get_or_create_category SELECT: SQL='{sql}', Params=(''{category_type}'', ''{category_name}'')")
    cursor.execute(sql, (category_type, category_name))
    result = cursor.fetchone()
    if result:
        return result[0]
    else:
        logger.info(f"DEBUG: 新しいカテゴリを作成します: Type='{category_type}', Name='{category_name}'")
        insert_sql = "INSERT INTO categories (type, name) VALUES (%s, %s)"
        logger.debug(f"DEBUG SQL: get_or_create_category INSERT: SQL='{insert_sql}', Params=(''{category_type}'', ''{category_name}'')")
        try:
            cursor.execute(insert_sql, (category_type, category_name))
            return cursor.lastrowid
        except mysql.connector.Error as err:
            if err.errno == 1062: # Duplicate entry for key 'uk_type_name'
                # 重複エラーの場合、すでに存在するので再取得を試みる
                logger.warning(f"DEBUG: カテゴリ '{category_type}' - '{category_name}' は既に存在するため、再取得します。")
                logger.debug(f"DEBUG SQL: get_or_create_category SELECT (after retry): SQL='{sql}', Params=(''{category_type}'', ''{category_name}'')")
                cursor.execute(sql, (category_type, category_name))
                result_after_retry = cursor.fetchone()
                if result_after_retry:
                    return result_after_retry[0]
                else:
                    # ここに到達することは稀だが、もし再取得も失敗したらエラー
                    logger.error(f"ERROR: カテゴリ '{category_type}' - '{category_name}' の重複作成後の再取得に失敗しました。")
                    raise
            else:
                raise # その他のDBエラーは再スロー

def associate_product_with_category(cursor, conn, product_db_id: int, category_id: int):
    """
    product_idとcategory_idをproduct_categoriesテーブルに紐付ける。
    重複挿入を避ける。
    """
    insert_sql = "INSERT IGNORE INTO product_categories (product_id, category_id) VALUES (%s, %s)"
    logger.debug(f"DEBUG SQL: associate_product_with_category INSERT: SQL='{insert_sql}', Params=({product_db_id}, {category_id})")
    cursor.execute(insert_sql, (product_db_id, category_id))
    # conn.commit() は populate_products_and_categories_main_loop の main commit でまとめて行う (変更なし)


def ensure_processed_at_column_exists(cursor, conn):
    """
    raw_api_dataテーブルにprocessed_atカラムが存在することを確認し、なければ追加する。
    """
    try:
        cursor.execute("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = 'raw_api_data' AND COLUMN_NAME = 'processed_at'", (DB_CONFIG['database'],))
        if cursor.fetchone() is None:
            logger.info("raw_api_dataテーブルにprocessed_atカラムを追加します...")
            alter_sql = "ALTER TABLE `raw_api_data` ADD COLUMN `processed_at` DATETIME NULL COMMENT 'products/categoriesへの処理が完了した日時 (NULLの場合は未処理)'"
            cursor.execute(alter_sql)
            conn.commit()
            logger.info("processed_atカラムが正常に追加されました。")
        else:
            logger.info("processed_atカラムは既に存在します。")
    except mysql.connector.Error as err:
        if "Duplicate column name 'processed_at'" not in str(err):
            logger.error(f"processed_atカラムの確認または追加エラー: {err}")
            raise # その他のエラーは再スローする

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
    logger.debug(f"DEBUG SQL: process_single_product_id_batch SELECT RAW: SQL='{select_raw_sql}', Params=(''{product_api_id}'', ''{source_api_name}'')")
    cursor.execute(select_raw_sql, (product_api_id, source_api_name))
    all_raw_data_for_product = cursor.fetchall()

    if not all_raw_data_for_product:
        logger.debug(f"DEBUG: 処理すべきraw_api_dataが見つかりませんでした。Product ID: {product_api_id}, Source: {source_api_name}")
        return 0 # 処理すべきデータがなければ0を返す

    # productsテーブルを更新するための「メイン」となる生データを選択
    # ここでは最新の fetched_at を持つものをメインとする
    main_raw_data_row = all_raw_data_for_product[0]
    main_raw_api_data_id = main_raw_data_row[0]

    main_raw_json_data = json.loads(main_raw_data_row[1])
    main_item_data = main_raw_json_data # Duga APIのJSONはitemキーの中に直接データがあるため

    logger.debug(f"DEBUG: メインのitem_data (product_id: {product_api_id}): {main_item_data}")

    # 全てのraw_api_dataレコードからカテゴリ情報を収集
    collected_genres = set()
    collected_actresses = set()
    collected_series_names = set() # シリーズ名は文字列で収集

    # 画像URLの候補を収集 (優先順位: large -> medium -> small)
    main_image_candidates = []
    og_image_candidates = [] # OGP画像もメイン画像と同じロジックで収集

    for raw_data_row in all_raw_data_for_product:
        current_raw_json_data = json.loads(raw_data_row[1])
        current_item_data = current_raw_json_data # Duga APIのJSONはitemキーの中に直接データがあるため

        logger.debug(f"DEBUG: カテゴリ収集元のitem_data: {current_item_data}")

        # ジャンル収集 (Duga APIの 'category' -> 'data' に対応)
        categories_raw = get_safe_value(current_item_data, ['category'])
        genres_to_process = []

        if isinstance(categories_raw, list):
            for entry in categories_raw:
                if isinstance(entry, dict) and 'data' in entry:
                    if isinstance(entry['data'], dict) and 'name' in entry['data']:
                        genres_to_process.append(entry['data'])
                    elif isinstance(entry['data'], list):
                        genres_to_process.extend(entry['data'])
        elif isinstance(categories_raw, dict) and 'data' in categories_raw:
            if isinstance(categories_raw['data'], list):
                genres_to_process.extend(categories_raw['data'])
            elif isinstance(categories_raw['data'], dict) and 'name' in categories_raw['data']:
                genres_to_process.append(categories_raw['data'])

        logger.debug(f"DEBUG: 抽出された genres_data (from category.data processing): {genres_to_process} (タイプ: {type(genres_to_process)})")
        if genres_to_process:
            for genre_entry in genres_to_process:
                if isinstance(genre_entry, dict) and 'name' in genre_entry:
                    genre_name = clean_string(genre_entry['name'])
                    if genre_name:
                        collected_genres.add(genre_name)

        # 女優収集 (Duga APIの 'performer' -> 'data' に対応)
        performers_raw = get_safe_value(current_item_data, ['performer'])
        actresses_to_process = []

        if isinstance(performers_raw, list):
            for entry in performers_raw:
                if isinstance(entry, dict) and 'data' in entry:
                    if isinstance(entry['data'], dict) and 'name' in entry['data']:
                        actresses_to_process.append(entry['data'])
                    elif isinstance(entry['data'], list):
                        actresses_to_process.extend(entry['data'])
        elif isinstance(performers_raw, dict) and 'data' in performers_raw:
            if isinstance(performers_raw['data'], list):
                actresses_to_process.extend(performers_raw['data'])
            elif isinstance(performers_raw['data'], dict) and 'name' in performers_raw['data']:
                actresses_to_process.append(performers_raw['data'])

        logger.debug(f"DEBUG: 抽出された actresses_data (from performer.data processing): {actresses_to_process} (タイプ: {type(actresses_to_process)})")
        if actresses_to_process:
            for actress_entry in actresses_to_process:
                if isinstance(actress_entry, dict) and 'name' in actress_entry:
                    actress_name = clean_string(actress_entry['name'])
                    if actress_name:
                        collected_actresses.add(actress_name)

        # シリーズ収集 (Duga APIの 'series' -> 'name' に対応)
        series_name_from_item = clean_string(get_safe_value(current_item_data, ['series', 'name']))
        if series_name_from_item:
            collected_series_names.add(series_name_from_item)

        # 画像URL候補の収集
        # posterimageとjacketimageから各サイズのURLを収集し、優先順位をつけて追加
        # large > medium > small の順で優先
        
        # posterimageからの候補
        poster_images = get_safe_value(current_item_data, ['posterimage'], [])
        if poster_images and isinstance(poster_images, list) and len(poster_images) > 0:
            if get_safe_value(poster_images[0], ['large']): main_image_candidates.append(get_safe_value(poster_images[0], ['large']))
            if get_safe_value(poster_images[0], ['midium']): main_image_candidates.append(get_safe_value(poster_images[0], ['midium']))
            if get_safe_value(poster_images[0], ['small']): main_image_candidates.append(get_safe_value(poster_images[0], ['small']))

        # jacketimageからの候補
        jacket_images = get_safe_value(current_item_data, ['jacketimage'], [])
        if jacket_images and isinstance(jacket_images, list) and len(jacket_images) > 0:
            if get_safe_value(jacket_images[0], ['large']): main_image_candidates.append(get_safe_value(jacket_images[0], ['large']))
            if get_safe_value(jacket_images[0], ['midium']): main_image_candidates.append(get_safe_value(jacket_images[0], ['midium']))
            if get_safe_value(jacket_images[0], ['small']): main_image_candidates.append(get_safe_value(jacket_images[0], ['small']))
        
        # OGP画像は、メイン画像と同じ候補リストを使用 (Duga APIに専用OGPフィールドがないため)
        og_image_candidates.extend(main_image_candidates)


    logger.debug(f"DEBUG: 最終的に収集されたジャンル: {collected_genres}")
    logger.debug(f"DEBUG: 最終的に収集された女優: {collected_actresses}")
    logger.debug(f"DEBUG: 最終的に収集されたシリーズ: {collected_series_names}")

    # productsテーブルに挿入するデータをメインの生データから抽出
    title = clean_string(get_safe_value(main_item_data, ['title']))
    logger.debug(f"DEBUG: 抽出されたタイトル (product_id: {product_api_id}): '{title}'")
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

    price = convert_to_float(get_safe_value(main_item_data, ['price'], default='0'))
    volume = convert_to_int(get_safe_value(main_item_data, ['volume']), default=0)
    url = clean_string(get_safe_value(main_item_data, ['url']))
    affiliate_url = clean_string(get_safe_value(main_item_data, ['affiliateurl']))

    sample_movie_url = clean_string(get_safe_value(main_item_data, ['samplemovie', 0, 'midium', 'movie']))
    sample_movie_capture_url = clean_string(get_safe_value(main_item_data, ['samplemovie', 0, 'midium', 'capture']))

    # メイン画像URLの選定 (重複を排除し、順番を保持)
    main_image_url = None
    if main_image_candidates:
        main_image_candidates_unique = list(dict.fromkeys(main_image_candidates)) 
        main_image_url = clean_string(main_image_candidates_unique[0])

    # OGP画像URLの選定
    og_image_url = None
    if og_image_candidates:
        og_image_candidates_unique = list(dict.fromkeys(og_image_candidates))
        og_image_url = clean_string(og_image_candidates_unique[0])

    source_api_for_products = source_api_name

    if not product_api_id:
        logger.warning(f"警告: product_id (raw_api_data.product_id) が空のためスキップします (Source: {source_api_name}).")
        for raw_data_row in all_raw_data_for_product:
            update_raw_processed_sql = "UPDATE raw_api_data SET processed_at = %s WHERE id = %s"
            logger.debug(f"DEBUG SQL: Skipping product - UPDATE RAW PROCESSED: SQL='{update_raw_processed_sql}', Params=(''{datetime.now()}'', {raw_data_row[0]})")
            cursor.execute(update_raw_processed_sql, (datetime.now(), raw_data_row[0]))
        return 0

    if not title: # titleがNoneまたは空文字列の場合もスキップ
        logger.warning(f"警告: 製品タイトルが空のためスキップします。Product ID: {product_api_id} (Source: {source_api_name}).")
        for raw_data_row in all_raw_data_for_product:
            update_raw_processed_sql = "UPDATE raw_api_data SET processed_at = %s WHERE id = %s"
            logger.debug(f"DEBUG SQL: Skipping product - UPDATE RAW PROCESSED: SQL='{update_raw_processed_sql}', Params=(''{datetime.now()}'', {raw_data_row[0]})")
            cursor.execute(update_raw_processed_sql, (datetime.now(), raw_data_row[0]))
        return 0


    # データベースに製品が存在するか確認
    select_product_sql = "SELECT id FROM products WHERE product_id = %s"
    logger.debug(f"DEBUG SQL: SELECT PRODUCT: SQL='{select_product_sql}', Params=(''{product_api_id}'')")
    cursor.execute(select_product_sql, (product_api_id,))
    existing_product = cursor.fetchone()

    now = datetime.now()
    product_db_id = None

    # JSONデータを文字列として準備
    genres_json_str = json.dumps(list(collected_genres), ensure_ascii=False) if collected_genres else None
    actresses_json_str = json.dumps(list(collected_actresses), ensure_ascii=False) if collected_actresses else None
    series_json_str = json.dumps(list(collected_series_names), ensure_ascii=False) if collected_series_names else None


    if existing_product:
        product_db_id = existing_product[0]
        update_query = """
            UPDATE products
            SET title = %s, original_title = %s, caption = %s, release_date = %s, maker_name = %s,
                item_no = %s, price = %s, volume = %s, url = %s, affiliate_url = %s,
                main_image_url = %s, og_image_url = %s, sample_movie_url = %s, sample_movie_capture_url = %s,
                actresses_json = %s, genres_json = %s, series_json = %s,
                source_api = %s, raw_api_data_id = %s, updated_at = %s
            WHERE product_id = %s
        """
        params = (
            title, original_title, caption, release_date, maker_name,
            item_no, price, volume, url, affiliate_url,
            main_image_url, og_image_url, sample_movie_url, sample_movie_capture_url,
            actresses_json_str, genres_json_str, series_json_str, # JSONカラム
            source_api_for_products, main_raw_api_data_id, now, product_api_id
        )
        logger.debug(f"DEBUG SQL: UPDATE PRODUCT: SQL='{update_query}', Params={params}")
        logger.debug(f"DEBUG SQL: UPDATE PRODUCT PARAM TYPES: {[type(p).__name__ for p in params]}") # パラメータの型をログ出力
        cursor.execute(update_query, params)
        logger.info(f"製品を更新しました: Product ID={product_api_id}, Title='{title}'")
    else:
        insert_query = """
            INSERT INTO products (
                product_id, title, original_title, caption, release_date, maker_name,
                item_no, price, volume, url, affiliate_url,
                main_image_url, og_image_url, sample_movie_url, sample_movie_capture_url,
                actresses_json, genres_json, series_json,
                source_api, raw_api_data_id, created_at, updated_at
            ) VALUES (
                %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s
            )
        """
        params = (
            product_api_id, title, original_title, caption, release_date, maker_name,
            item_no, price, volume, url, affiliate_url,
            main_image_url, og_image_url, sample_movie_url, sample_movie_capture_url,
            actresses_json_str, genres_json_str, series_json_str, # JSONカラム
            source_api_for_products, main_raw_api_data_id, now, now
        )
        logger.debug(f"DEBUG SQL: INSERT PRODUCT: SQL='{insert_query}', Params={params}")
        logger.debug(f"DEBUG SQL: INSERT PRODUCT PARAM TYPES: {[type(p).__name__ for p in params]}") # パラメータの型をログ出力
        cursor.execute(insert_query, params)
        product_db_id = cursor.lastrowid
        logger.info(f"新しい製品を挿入しました: Product ID={product_api_id}, Title='{title}'")

    # カテゴリの分類と紐付け (products.id が確定した後に行う)
    if product_db_id:
        # 収集したジャンルを紐付け
        if collected_genres:
            logger.debug(f"DEBUG: ジャンルを categories/product_categories に紐付けます。収集済み: {collected_genres}")
        for genre_name in collected_genres:
            category_id = get_or_create_category(cursor, conn, "ジャンル", genre_name)
            associate_product_with_category(cursor, conn, product_db_id, category_id)
            logger.info(f"  製品ID {product_api_id} にジャンル '{genre_name}' を紐付けました。")

        # 収集した女優を紐付け
        if collected_actresses:
            logger.debug(f"DEBUG: 女優を categories/product_categories に紐付けます。収集済み: {collected_actresses}")
        for actress_name in collected_actresses:
            category_id = get_or_create_category(cursor, conn, "女優", actress_name)
            associate_product_with_category(cursor, conn, product_db_id, category_id)
            logger.info(f"  製品ID {product_api_id} に女優 '{actress_name}' を紐付けました。")

        # レーベル (maker_name) を紐付け (メインデータから取得)
        if maker_name:
            logger.debug(f"DEBUG: レーベルを categories/product_categories に紐付けます。名前: '{maker_name}'")
            category_id = get_or_create_category(cursor, conn, "レーベル", maker_name)
            associate_product_with_category(cursor, conn, product_db_id, category_id)
            logger.info(f"  製品ID {product_api_id} にレーベル '{maker_name}' を紐付けました。")

        # 収集したシリーズを紐付け
        if collected_series_names: # シリーズ名セットを使用
            logger.debug(f"DEBUG: シリーズを categories/product_categories に紐付けます。収集済み: {collected_series_names}")
        for series_name in collected_series_names:
            category_id = get_or_create_category(cursor, conn, "シリーズ", series_name)
            associate_product_with_category(cursor, conn, product_db_id, category_id)
            logger.info(f"  製品ID {product_api_id} にシリーズ '{series_name}' を紐付けました。")

        # 処理済みのraw_api_dataレコードにマークを付ける
        for raw_data_row in all_raw_data_for_product:
            update_raw_processed_sql = "UPDATE raw_api_data SET processed_at = %s WHERE id = %s"
            logger.debug(f"DEBUG SQL: UPDATE RAW PROCESSED: SQL='{update_raw_processed_sql}', Params=(''{datetime.now()}'', {raw_data_row[0]})")
            cursor.execute(update_raw_processed_sql, (datetime.now(), raw_data_row[0]))

    return 1 # 処理した製品数を返すため


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

        # autocommitをFalseに設定し、明示的にトランザクションを管理
        conn.autocommit = False 

        # raw_api_dataテーブルにprocessed_atカラムが存在することを確認し、なければ追加する
        ensure_processed_at_column_exists(cursor, conn)
        
        # 未処理のユニークな (product_id, source_api) の組み合わせを取得
        select_distinct_sql = """
            SELECT DISTINCT product_id, source_api
            FROM raw_api_data
            WHERE processed_at IS NULL
            LIMIT 1000 
        """
        logger.debug(f"DEBUG SQL: SELECT DISTINCT PRODUCTS TO PROCESS: SQL='{select_distinct_sql}'")
        cursor.execute(select_distinct_sql)
        unique_product_ids_to_process = cursor.fetchall()

        if not unique_product_ids_to_process:
            logger.info("処理すべきユニークな製品データが見つかりませんでした。")
            return

        logger.info(f"raw_api_dataから {len(unique_product_ids_to_process)} 件のユニークな製品IDを処理します。")

        # 各ユニークな製品IDについて処理を実行
        for product_api_id, source_api_name in unique_product_ids_to_process:
            try:
                processed_this_product = process_single_product_id_batch(cursor, conn, product_api_id, source_api_name)
                if processed_this_product:
                    total_products_processed += processed_this_product
                conn.commit() # 各製品IDの処理後にコミット
                logger.info(f"製品ID {product_api_id} の処理とコミットが完了しました。")
            except Exception as e:
                logger.error(f"製品ID {product_api_id} の処理中にエラーが発生しました: {e}")
                if conn and conn.is_connected():
                    conn.rollback()
                    logger.warning("トランザクションをロールバックしました。")
                # エラーが発生した product_id の raw_api_data は processed_at が更新されないため、次回の実行で再度試行される

        logger.info(f"products および categories テーブルへのデータ投入が完了しました。総計 {total_products_processed} 件の製品を処理しました。")

    except mysql.connector.Error as err:
        logger.error(f"MySQL接続またはクエリ実行エラー: {err}")
        if conn and conn.is_connected():
            conn.rollback()
            logger.warning("メインループ中にトランザクションをロールバックしました。")
    except Exception as e:
        logger.error(f"予期せぬエラーが発生しました: {e}")
        if conn and conn.is_connected():
            conn.rollback()
            logger.warning("メインループ中にトランザクションをロールバックしました。")
    finally:
        if conn and conn.is_connected():
            cursor.close()
            conn.close()
            logger.info("MySQL接続を閉じました。")

# ==============================================================================
# メイン処理
# ==============================================================================

if __name__ == "__main__":
    # スクリプト実行前に既存のログファイルをクリア
    try:
        if os.path.exists(log_file_path):
            os.remove(log_file_path)
            # ロガーのハンドラをリセットして、新しいファイルに書き込めるようにする
            for handler in logger.handlers[:]: # リストをコピーしてイテレート
                if isinstance(handler, logging.FileHandler):
                    handler.close()
                    logger.removeHandler(handler)
            # ファイルハンドラを再設定
            new_file_handler = logging.FileHandler(log_file_path, mode='a', encoding='utf-8')
            new_file_handler.setLevel(logging.DEBUG)
            new_file_handler.setFormatter(file_formatter)
            logger.addHandler(new_file_handler)
            logger.info(f"既存のログファイル '{log_file_path}' をクリアしました。")
    except OSError as e:
        logger.error(f"ログファイルのクリア中にエラーが発生しました: {e}")

    conn_check = None
    try:
        conn_check = mysql.connector.connect(**DB_CONFIG)
        cursor_check = conn_check.cursor()

        # raw_api_dataテーブルにprocessed_atカラムが存在することを確認し、なければ追加する
        ensure_processed_at_column_exists(cursor_check, conn_check)

        # source_api='duga' で processed_at が NULL のデータがあるか確認
        cursor_check.execute("SELECT COUNT(*) FROM raw_api_data WHERE source_api = 'duga' AND processed_at IS NULL")
        if cursor_check.fetchone()[0] == 0:
            logger.info("raw_api_dataテーブルに処理すべき'duga'データがありません。ダミーデータを挿入します。(これはPHPスクリプトが動くまでのテスト用です)")
            # product_id が同じで、異なるカテゴリを持つダミーデータを複数挿入
            dummy_api_data_1_raw = {
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
                "category": [{"data": {"id": "GEN01", "name": "ジャンルA"}}],
                "performer": [{"data": {"id": "ACT01", "name": "女優X"}}],
                "series": {"name": "シリーズS1"},
                "label": {"id": "LBL01", "name": "レーベルX"}
            }
            dummy_api_data_2_raw = { # 同じproductidだが、カテゴリが異なる
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
                "category": [{"data": {"id": "GEN02", "name": "ジャンルB"}}], # 異なるジャンル
                "performer": [{"data": {"id": "ACT02", "name": "女優Y"}}],    # 異なる女優
                "series": {"name": "シリーズS2"},                                # 異なるシリーズ
                "label": {"id": "LBL01", "name": "レーベルX"}
            }
            dummy_api_data_3_raw = {
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
                "category": [{"data": {"id": "GEN03", "name": "ジャンルC"}}],
                "performer": [{"data": {"id": "ACT03", "name": "女優Z"}}],
                "series": {"name": "シリーズS3"},
                "label": {"id": "LBL02", "name": "レーベルY"}
            }
            # priceが数値のみのケース (Pythonの price 変換ロジックテスト用)
            dummy_api_data_4_raw = {
                "productid": "TEST_AGG_003",
                "title": "価格数値テスト",
                "releasedate": "2024-04-01",
                "makername": "テストメーカーM",
                "price": 1500, # 価格が数値で提供される場合
                "url": "http://example.com/test/3",
                "category": [{"data": {"name": "テストジャンル"}}],
                "performer": [{"data": {"name": "テスト女優"}}]
            }
            # ジャンル、女優が空、または存在しないケースのテスト
            dummy_api_data_5_raw = {
                "productid": "TEST_AGG_004",
                "title": "カテゴリなしテスト",
                "releasedate": "2024-05-01",
                "makername": "ノーカテゴリ",
                "price": "500円",
                "url": "http://example.com/test/4"
                # category, performer, series, label は意図的に含めない
            }

            insert_raw_api_data_dummy(conn_check, dummy_api_data_1_raw, 'duga', 'TEST_AGG_001')
            insert_raw_api_data_dummy(conn_check, dummy_api_data_2_raw, 'duga', 'TEST_AGG_001')
            insert_raw_api_data_dummy(conn_check, dummy_api_data_3_raw, 'duga', 'TEST_AGG_002')
            insert_raw_api_data_dummy(conn_check, dummy_api_data_4_raw, 'duga', 'TEST_AGG_003')
            insert_raw_api_data_dummy(conn_check, dummy_api_data_5_raw, 'duga', 'TEST_AGG_004')
            logger.info("カテゴリ集約テスト用のダミーデータをraw_api_dataテーブルに挿入しました。")
        else:
            logger.info("raw_api_dataテーブルに処理すべき'duga'データが既に存在します。")
    except Exception as e:
        logger.error(f"ダミーデータ挿入チェックまたは挿入エラー: {e}")
    finally:
        if conn_check and conn_check.is_connected():
            cursor_check.close()
            conn_check.close()
            logger.info("MySQL接続を閉じました。(ダミーデータチェック)")

    # products および categories テーブルへのデータ投入を実行
    populate_products_and_categories_main_loop()

# insert_raw_api_data_dummy 関数は main ブロックでのみ使用される仮の関数です
def insert_raw_api_data_dummy(conn, item_json_data, source_api_name, product_id_val):
    cursor = conn.cursor()
    now = datetime.now()
    # PHPスクリプトが保存する形式 (itemの内容を直接JSON文字列として) に合わせる
    # item_json_data_wrapper は {"item": {...}} 形式を想定
    # この関数は、`item`キーの下のデータそのものを受け取るように変更
    data_json_str = json.dumps(item_json_data, ensure_ascii=False) 

    insert_query = """
        INSERT INTO raw_api_data (product_id, api_response_data, source_api, fetched_at, updated_at, processed_at)
        VALUES (%s, %s, %s, %s, %s, NULL)
    """
    logger.debug(f"DEBUG SQL: INSERT RAW DUMMY: SQL='{insert_id_val}'', ''{data_json_str[:100]}...'', ''{source_api_name}'', ''{now}'', ''{now}'')")
    cursor.execute(insert_query, (product_id_val, data_json_str, source_api_name, now, now))
    conn.commit()
    logger.info(f"raw_api_data (ダミー) を挿入しました (product_id: {product_id_val}, source_api: {source_api_name})。")
