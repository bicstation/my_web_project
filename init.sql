-- USE tiper; -- 必要であればデータベースを選択

-- 既存のテーブルが存在する場合は削除します。
-- 外部キー制約の依存関係があるため、削除順序が非常に重要です。
-- 依存しているテーブルから先に削除します。

-- products テーブルに依存している可能性のあるテーブルを削除
DROP TABLE IF EXISTS `product_categories`;
-- users テーブルに依存している可能性のあるテーブルを削除
DROP TABLE IF EXISTS `media`;
DROP TABLE IF EXISTS `link_clicks`;

-- 主なデータテーブルの削除 (依存関係のないものから)
DROP TABLE IF EXISTS `products`;
-- raw_api_data から UNIQUE KEY を削除するため、一度DROPしてからCREATEします。
DROP TABLE IF EXISTS `raw_api_data`; 
DROP TABLE IF EXISTS `categories`; -- categories も削除順に追加
DROP TABLE IF EXISTS `sessions`;
DROP TABLE IF EXISTS `users`;


-- 1. users テーブル
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` VARCHAR(50) DEFAULT 'user', -- 'user' または 'admin' などの役割
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ユーザー情報を管理するテーブル';

-- 2. sessions テーブル
CREATE TABLE IF NOT EXISTS `sessions` (
    `session_id` VARCHAR(255) NOT NULL PRIMARY KEY,
    `user_id` INT NULL, -- NULLを許容し、非ログインユーザーのセッションも可能に
    `data` MEDIUMTEXT, -- セッションデータを格納 (例: PHPのセッションデータ)
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ユーザーセッション情報を管理するテーブル';

-- 3. raw_api_data テーブル (API生データ保存用) - UNIQUE KEYを削除し、processed_atを追加
CREATE TABLE IF NOT EXISTS `raw_api_data` (
    `id` INT AUTO_INCREMENT PRIMARY KEY COMMENT '主キー',
    `product_id` VARCHAR(255) NOT NULL COMMENT 'APIから取得したプロダクトID。重複を許可。',
    `api_response_data` JSON NOT NULL COMMENT 'APIからの生レスポンスデータ全体をJSON形式で保存',
    `source_api` VARCHAR(50) NOT NULL COMMENT 'データの取得元API (例: "duga", "mgs")。PHPスクリプトのAPI_SOURCE_NAMEに対応。',
    `fetched_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'データ取得日時',
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'レコードが最後に更新された日時',
    `processed_at` DATETIME NULL COMMENT 'products/categoriesへの処理が完了した日時 (NULLの場合は未処理)',
    -- UNIQUE KEY `idx_product_id_source_api` を削除しました。
    -- 代わりに非ユニークなインデックスを設けます。
    INDEX `idx_product_id_source_api` (`product_id`, `source_api`) COMMENT 'product_idとsource_apiの組み合わせでの検索効率を上げるためのインデックス',
    INDEX `idx_source_api_processed` (`source_api`, `processed_at`) COMMENT 'ソースAPIと処理状況での検索効率を上げるインデックス'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='APIから取得した生データを格納するテーブル';


-- 4. products テーブル (整形された商品データ用) - 画像カラムを簡素化し、og_imageを追加、カテゴリJSONカラム追加
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY COMMENT '主キー',
    `product_id` VARCHAR(255) NOT NULL UNIQUE COMMENT 'API側で使用する一意のプロダクトID',
    `title` VARCHAR(255) NOT NULL COMMENT '商品タイトル',
    `original_title` VARCHAR(255) NULL COMMENT '原題など、元のタイトル',
    `caption` TEXT NULL COMMENT '商品説明やキャプション',
    `release_date` DATE NULL COMMENT 'リリース日',
    `maker_name` VARCHAR(255) NULL COMMENT 'メーカー名（レーベル）', -- 既存のmaker_nameをレーベルとして使用
    `item_no` VARCHAR(255) NULL COMMENT '商品番号',
    `price` DECIMAL(10, 2) NULL COMMENT '価格',
    `volume` INT NULL COMMENT '巻数、枚数など',
    `url` VARCHAR(1024) NULL COMMENT '商品詳細ページへのURL', -- VARCHAR(2048) から VARCHAR(1024) に変更
    `affiliate_url` VARCHAR(1024) NULL COMMENT 'アフィリエイトURL', -- VARCHAR(2048) から VARCHAR(1024) に変更
    `main_image_url` VARCHAR(1024) NULL COMMENT 'メイン画像URL（各サイズの表示はアプリケーション側で生成）', -- VARCHAR(2048) から VARCHAR(1024) に変更
    `og_image_url` VARCHAR(1024) NULL COMMENT 'Open Graph画像URL（SNS共有用など）', -- VARCHAR(2048) から VARCHAR(1024) に変更
    `sample_movie_url` VARCHAR(1024) NULL COMMENT 'サンプル動画URL', -- VARCHAR(2048) から VARCHAR(1024) に変更
    `sample_movie_capture_url` VARCHAR(1024) NULL COMMENT 'サンプル動画キャプチャ画像URL', -- VARCHAR(2048) から VARCHAR(1024) に変更
    
    -- ★★★ ここから追加/変更カラム ★★★
    `actresses_json` JSON NULL COMMENT '関連女優名をJSON配列で保存',
    `genres_json` JSON NULL COMMENT '関連ジャンル名をJSON配列で保存',
    `series_json` JSON NULL COMMENT '関連シリーズ名をJSONで保存',
    -- ★★★ ここまで追加/変更カラム ★★★

    `source_api` VARCHAR(50) NOT NULL COMMENT 'データの取得元API',
    `raw_api_data_id` INT NULL, -- raw_api_dataテーブルへの外部キー
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`raw_api_data_id`) REFERENCES `raw_api_data`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='整形された商品情報を管理するテーブル';

-- 5. categories テーブル (大カテゴリ、中カテゴリ用)
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY COMMENT '主キー',
    `type` VARCHAR(50) NOT NULL COMMENT '大カテゴリ (例: "ジャンル", "女優", "レーベル", "シリーズ")',
    `name` VARCHAR(255) NOT NULL COMMENT '中カテゴリ名 (例: "アクション", "田中", "SOD", "Best Series")',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_type_name` (`type`, `name`) COMMENT 'カテゴリ名とタイプで重複を禁止'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品カテゴリを管理するテーブル';

-- 6. product_categories 中間テーブル (products と categories の多対多関係)
CREATE TABLE IF NOT EXISTS `product_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY COMMENT '主キー',
    `product_id` INT NOT NULL COMMENT 'products テーブルの ID',
    `category_id` INT NOT NULL COMMENT 'categories テーブルの ID',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_product_category` (`product_id`, `category_id`) COMMENT '同じ商品に同じカテゴリが複数紐付くのを禁止',
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品とカテゴリの関連を管理する中間テーブル';

-- 7. link_clicks テーブル
CREATE TABLE IF NOT EXISTS `link_clicks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` VARCHAR(255) NOT NULL COMMENT 'クリックされたプロダクトのID',
    `click_type` VARCHAR(50) NOT NULL COMMENT 'クリックの種類 (例: "affiliate_purchase", "detail_view", "banner_click")',
    `referrer` VARCHAR(1024) NULL COMMENT '参照元URL', -- VARCHAR(2048) から VARCHAR(1024) に変更
    `user_agent` VARCHAR(512) NULL COMMENT 'ユーザーエージェント文字列',
    `ip_address` VARCHAR(45) NULL COMMENT 'クリック元のIPアドレス',
    `user_id` INT NULL COMMENT 'クリックしたユーザーのID (ログイン時のみ)',
    `click_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'クリック日時',
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='リンククリックイベントを記録するテーブル';


-- 8. media テーブル
CREATE TABLE IF NOT EXISTS `media` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(1024) NOT NULL, -- VARCHAR(2048) から VARCHAR(1024) に変更
    `mime_type` VARCHAR(100) NOT NULL,
    `file_size` BIGINT NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ユーザーがアップロードしたメディアファイルを管理するテーブル';

