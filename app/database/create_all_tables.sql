-- データベースを使用
USE tiper;

-- ----------------------------------------------------
-- 既存のテーブルを外部キー依存関係の順序で削除します
-- (依存しているテーブルから先に削除)
-- ----------------------------------------------------

-- productsテーブルに依存する中間テーブル
DROP TABLE IF EXISTS `product_categories`;
DROP TABLE IF EXISTS `product_genres`;
DROP TABLE IF EXISTS `product_labels`;
DROP TABLE IF EXISTS `product_directors`;
DROP TABLE IF EXISTS `product_series`;
DROP TABLE IF EXISTS `product_actors`;

-- productsテーブルに依存するクリックログテーブル
DROP TABLE IF EXISTS `link_clicks`;

-- raw_api_dataに依存するproductsテーブル
DROP TABLE IF EXISTS `products`;

-- (usersに依存するmediaテーブルがあれば削除 - 今回のスキーマには明示されていませんが、以前の言及を考慮)
DROP TABLE IF EXISTS `media`; -- 以前の会話でER図にmediaテーブルの記載があったため、念のためここに含めます。

-- 独立した分類テーブル (中間テーブルからの参照がなくなったので削除可能)
DROP TABLE IF EXISTS `genres`;
DROP TABLE IF EXISTS `labels`;
DROP TABLE IF EXISTS `directors`;
DROP TABLE IF EXISTS `series`;
DROP TABLE IF EXISTS `actors`;
DROP TABLE IF EXISTS `categories`; -- 自己参照FKがあっても、依存する中間テーブルが削除されれば単独で削除可能

-- その他の独立したテーブル
DROP TABLE IF EXISTS `raw_api_data`;
DROP TABLE IF EXISTS `sessions`;
DROP TABLE IF EXISTS `users`;


-- ----------------------------------------------------
-- 新しいテーブルを依存関係の順序で作成します
-- ----------------------------------------------------

-- 1. users テーブルの作成
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` VARCHAR(50) DEFAULT 'user', -- 'user' または 'admin' などの役割
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. sessions テーブルの作成 (カスタムセッションハンドラ用)
CREATE TABLE `sessions` (
    `session_id` VARCHAR(255) NOT NULL PRIMARY KEY,
    `data` MEDIUMTEXT, -- セッションデータ
    `expires_at` INT UNSIGNED NOT NULL, -- UNIXタイムスタンプで有効期限を保存
    INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. raw_api_data テーブルの作成 (API生データ保存用)
CREATE TABLE `raw_api_data` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `source_name` VARCHAR(50) NOT NULL,
    `api_product_id` VARCHAR(255) NOT NULL,
    `row_json_data` JSON,
    `fetched_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_source_api_product_id` (`source_name`, `api_product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. products テーブルの作成 (整形された商品データ保存用)
-- product_id に UNIQUE 制約を付けて、概念的な商品を一意に識別できるようにします。
CREATE TABLE `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` VARCHAR(255) NOT NULL UNIQUE, -- 概念的な商品を一意に識別するID
    `title` VARCHAR(255) NOT NULL,
    `release_date` DATE,
    `maker_name` VARCHAR(255),
    `genre` VARCHAR(255), -- APIのオリジナルジャンル名（genresテーブルとは別に保持）
    `url` VARCHAR(2048), -- アフィリエイトリンクまたは外部サイトの商品詳細URL
    `image_url` VARCHAR(2048), -- 表示用の商品画像のURL（整形済み）
    `video_url` VARCHAR(2048), -- ★追加：動画のURL
    `price` DECIMAL(10, 2), -- ★追加：価格（小数点以下2桁まで、最大10桁）
    `source_api` VARCHAR(50) NOT NULL,
    `raw_api_data_id` INT, -- raw_api_dataテーブルへの外部キー
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`raw_api_data_id`) REFERENCES `raw_api_data`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. categories テーブルの作成 (商品カテゴリ用 - 階層対応)
CREATE TABLE `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL UNIQUE,
    `slug` VARCHAR(255) NOT NULL UNIQUE, -- URLフレンドリーなカテゴリ名
    `description` TEXT,
    `parent_id` INT NULL, -- 自己参照FK: 親カテゴリのid
    `level` INT NOT NULL DEFAULT 0, -- 階層レベル (0: 大分類, 1: 中分類, 2: 小分類)
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`parent_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. genres テーブルの作成 (商品ジャンル用 - 単一階層)
CREATE TABLE `genres` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL UNIQUE,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `description` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. labels テーブルの作成 (商品レーベル用 - 単一階層)
CREATE TABLE `labels` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL UNIQUE,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `description` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. directors テーブルの作成 (監督用 - 単一階層)
CREATE TABLE `directors` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL UNIQUE,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `description` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. series テーブルの作成 (シリーズ用 - 単一階層)
CREATE TABLE `series` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL UNIQUE,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `description` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. actors テーブルの作成 (女優用 - 単一階層)
CREATE TABLE `actors` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL UNIQUE,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `description` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------
-- 中間テーブルの作成 (products と 各分類項目の多対多を結合)
-- ----------------------------------------------------

-- 11. product_categories (products と categories)
CREATE TABLE `product_categories` (
    `product_id` VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci,
    `category_id` INT NOT NULL,
    PRIMARY KEY (`product_id`, `category_id`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. product_genres (products と genres)
CREATE TABLE `product_genres` (
    `product_id` VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci,
    `genre_id` INT NOT NULL,
    PRIMARY KEY (`product_id`, `genre_id`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE,
    FOREIGN KEY (`genre_id`) REFERENCES `genres`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. product_labels (products と labels)
CREATE TABLE `product_labels` (
    `product_id` VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci,
    `label_id` INT NOT NULL,
    PRIMARY KEY (`product_id`, `label_id`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE,
    FOREIGN KEY (`label_id`) REFERENCES `labels`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. product_directors (products と directors)
CREATE TABLE `product_directors` (
    `product_id` VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci,
    `director_id` INT NOT NULL,
    PRIMARY KEY (`product_id`, `director_id`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE,
    FOREIGN KEY (`director_id`) REFERENCES `directors`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. product_series (products と series)
CREATE TABLE `product_series` (
    `product_id` VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci,
    `series_id` INT NOT NULL,
    PRIMARY KEY (`product_id`, `series_id`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE,
    FOREIGN KEY (`series_id`) REFERENCES `series`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. product_actors (products と actors)
CREATE TABLE `product_actors` (
    `product_id` VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci,
    `actor_id` INT NOT NULL,
    PRIMARY KEY (`product_id`, `actor_id`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE,
    FOREIGN KEY (`actor_id`) REFERENCES `actors`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 17. link_clicks テーブルの作成 (クリックログ用)
CREATE TABLE `link_clicks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci, -- クリックされたプロダクトの概念ID
    `click_type` VARCHAR(50) NOT NULL, -- 例: 'affiliate_purchase', 'detail_view', 'banner_click' など
    `referrer` VARCHAR(2048),          -- 参照元URL
    `user_agent` VARCHAR(512),         -- ユーザーエージェント文字列
    `ip_address` VARCHAR(45),          -- クリック元のIPアドレス (IPv4で15文字、IPv6で45文字まで)
    `clicked_at` DATETIME DEFAULT CURRENT_TIMESTAMP, -- クリック日時
    `redirect_url` VARCHAR(2048),      -- クリックされたリンクの実際のURL
    INDEX `idx_product_id` (`product_id`),
    INDEX `idx_clicked_at` (`clicked_at`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------
-- 初期データの挿入 (任意)
-- ----------------------------------------------------

-- adminユーザーが存在しない場合のみ挿入
-- パスワード '1492nabe' のハッシュ値
-- (PHPの password_hash('1492nabe', PASSWORD_BCRYPT) で生成)
-- このハッシュ値は実行環境や時刻によって異なるため、例として生成したものを記載します。
-- 正確なハッシュ値を生成するにはPHPで一度実行する必要があります。
-- ここでは仮のハッシュ値を記述します。
INSERT IGNORE INTO `users` (`username`, `email`, `password_hash`, `role`) VALUES
('admin', 'admin@tipers.live', '$2y$10$wN3X2Y7J5K0H8F1E9D7C4B6A3I8L0P9Q2R4S6T8U1V3W5X7Y9Z0A1B2C3D4E5F6G7H9', 'admin');
-- ↑上記のハッシュ値はあくまで例です。本番環境では必ず PHP の `password_hash()` で生成した安全なハッシュ値を使用してください。
-- 例えば、PHP CLI で `echo password_hash('1492nabe', PASSWORD_BCRYPT);` を実行して得られた値を使用します。


-- カテゴリのダミーデータ (大分類 level 0 と中分類 level 1 のみ)
-- IDを明示的に指定して、product_categories で参照しやすくします
INSERT INTO `categories` (`id`, `name`, `slug`, `parent_id`, `level`) VALUES
(1, 'デジタル家電', 'digital-appliances', NULL, 0),
(2, '書籍', 'books', NULL, 0),
(3, 'ファッション', 'fashion', NULL, 0),
(4, 'ガジェット', 'gadgets', NULL, 0), -- これも大分類として扱われる
(5, 'スマートフォン', 'smartphones', 1, 1), -- デジタル家電の子
(6, 'PC周辺機器', 'pc-accessories', 1, 1), -- デジタル家電の子
(7, '技術書', 'tech-books', 2, 1), -- 書籍の子
(8, '小説', 'novels', 2, 1), -- 書籍の子
(9, 'メンズウェア', 'menswear', 3, 1), -- ファッションの子
(10, 'レディースウェア', 'womenswear', 3, 1); -- ファッションの子


-- ジャンルのダミーデータ
INSERT INTO `genres` (`id`, `name`, `slug`) VALUES
(1, 'SF', 'sci-fi'),
(2, 'ファンタジー', 'fantasy'),
(3, 'コメディ', 'comedy'),
(4, 'アクション', 'action');

-- レーベルのダミーデータ
INSERT INTO `labels` (`id`, `name`, `slug`) VALUES
(1, 'エイトスター', 'eight-star'),
(2, 'ミライ映像', 'mirai-eizo');

-- 監督のダミーデータ
INSERT INTO `directors` (`id`, `name`, `slug`) VALUES
(1, '山田太郎', 'yamada-taro'),
(2, '鈴木花子', 'suzuki-hanako');

-- シリーズのダミーデータ
INSERT INTO `series` (`id`, `name`, `slug`) VALUES
(1, '未来都市', 'future-city'),
(2, '探偵物語', 'detective-story');

-- 女優のダミーデータ
INSERT INTO `actors` (`id`, `name`, `slug`) VALUES
(1, '田中美咲', 'tanaka-misaki'),
(2, '佐藤ひかり', 'sato-hikari');


-- raw_api_data のダミーデータ (products に紐づく生データ)
INSERT INTO `raw_api_data` (`id`, `source_name`, `api_product_id`, `row_json_data`) VALUES
(1, 'API_A', 'PROD_XYZ_001', '{"title": "高性能スマホX", "price": 80000, "image_high_res_url": "https://example.com/images/phone-x-high.jpg", "affiliate_link": "https://example.com/phone-x-affiliate", "video_direct_url": "https://www.youtube.com/embed/example-phone-x-video"}'),
(2, 'API_A', 'PROD_ABC_002', '{"title": "詳解PHPプログラミング", "price": 3000, "image_thumb_url": "https://example.com/images/php-book-thumb.jpg", "affiliate_link": "https://example.com/php-book-affiliate"}'),
(3, 'API_B', 'PROD_DEF_003', '{"title": "新世代スマートウォッチ", "item_price": "¥15,000", "image_id": "watch_003_img", "affiliate_link": "https://siteb.com/watch-affiliate-link"}');


-- products のダミーデータ (概念的な商品データ)
-- product_id が概念的な商品を一意に識別します。
-- video_url と price にもダミーデータを挿入
INSERT INTO `products` (`product_id`, `title`, `release_date`, `maker_name`, `genre`, `url`, `image_url`, `video_url`, `price`, `source_api`, `raw_api_data_id`) VALUES
('P001-SMARTPHONE', '高性能スマートフォンX', '2023-01-01', 'TechCorp', 'スマートフォン', 'https://example.com/phone-x-affiliate', 'https://example.com/images/phone-x-high.jpg', 'https://www.youtube.com/embed/example-phone-x-video', 80000.00, 'API_A', 1),
('P002-BOOK-PHP', '詳解PHPプログラミング', '2022-11-01', 'IT出版', 'プログラミング', 'https://example.com/php-book-affiliate', 'https://example.com/images/php-book-thumb.jpg', NULL, 3000.00, 'API_A', 2),
('P003-SMARTWATCH', '新世代スマートウォッチ', '2024-03-15', 'GadgetZone', 'ウェアラブル', 'https://siteb.com/watch-affiliate-link', 'https://images.siteb.com/products/large/watch_003_img.jpg', NULL, 15000.00, 'API_B', 3);


-- product_categories のダミーデータ (product_id と category_id の関連付け)
INSERT INTO `product_categories` (`product_id`, `category_id`) VALUES
('P001-SMARTPHONE', 1), -- デジタル家電 (大分類)
('P001-SMARTPHONE', 5), -- スマートフォン (中分類)
('P002-BOOK-PHP', 2), -- 書籍 (大分類)
('P002-BOOK-PHP', 7), -- 技術書 (中分類)
('P003-SMARTWATCH', 1), -- デジタル家電 (大分類)
('P003-SMARTWATCH', 4); -- ガジェット (大分類だが、ここではウェアラブルとして想定)

-- product_genres のダミーデータ
INSERT INTO `product_genres` (`product_id`, `genre_id`) VALUES
('P001-SMARTPHONE', 4), -- アクション (例として)
('P002-BOOK-PHP', 1), -- SF (例として)
('P003-SMARTWATCH', 3); -- コメディ (例として)

-- product_labels のダミーデータ
INSERT INTO `product_labels` (`product_id`, `label_id`) VALUES
('P001-SMARTPHONE', 1), -- エイトスター
('P003-SMARTWATCH', 2); -- ミライ映像

-- product_directors のダミーデータ
INSERT INTO `product_directors` (`product_id`, `director_id`) VALUES
('P001-SMARTPHONE', 1); -- 山田太郎

-- product_series のダミーデータ
INSERT INTO `product_series` (`product_id`, `series_id`) VALUES
('P001-SMARTPHONE', 1); -- 未来都市

-- product_actors のダミーデータ
INSERT INTO `product_actors` (`product_id`, `actor_id`) VALUES
('P001-SMARTPHONE', 1); -- 田中美咲


-- link_clicks のダミーデータ
INSERT INTO `link_clicks` (`product_id`, `click_type`, `referrer`, `user_agent`, `ip_address`, `redirect_url`) VALUES
('P001-SMARTPHONE', 'affiliate_purchase', 'https://duga.tiper.live/duga_product_detail.php?product_id=P001-SMARTPHONE', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36', '192.168.1.100', 'https://example.com/phone-x-affiliate'),
('P002-BOOK-PHP', 'detail_view', 'https://duga.tiper.live/', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15', '203.0.113.1', 'https://duga.tiper.live/duga_product_detail.php?product_id=P002-BOOK-PHP'),
('P003-SMARTWATCH', 'affiliate_purchase', 'https://duga.tiper.live/duga_product_detail.php?product_id=P003-SMARTWATCH', 'Mozilla/5.0 (Linux; Android 10) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36', '10.0.0.5', 'https://siteb.com/watch-affiliate-link');