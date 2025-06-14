-- 既存のテーブルが存在する場合は削除します。
-- 外部キー制約の依存関係があるため、削除順序が非常に重要です。
-- 依存しているテーブルから先に削除します。

-- 万が一、スペルミスで 'row_api_data' が作成されていた場合のために削除
DROP TABLE IF EXISTS `row_api_data`;

-- media テーブルが存在し、users テーブルに依存している場合は先に削除
DROP TABLE IF EXISTS `media`;

-- product_categories テーブルが存在し、products テーブルに依存している場合は先に削除
DROP TABLE IF EXISTS `product_categories`;

-- products テーブルを削除
DROP TABLE IF EXISTS `products`;

-- raw_api_data テーブルを削除 (正しいテーブル名)
DROP TABLE IF EXISTS `raw_api_data`;

-- users テーブルを削除
DROP TABLE IF EXISTS `users`;


-- ここからテーブル作成

-- raw_api_data テーブルが存在しない場合に作成
CREATE TABLE IF NOT EXISTS `raw_api_data` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `source_name` VARCHAR(50) NOT NULL,
    `api_product_id` VARCHAR(255) NOT NULL,
    `row_json_data` JSON,
    `fetched_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_source_api_product_id` (`source_name`, `api_product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- products テーブルが存在しない場合に作成 (raw_api_data に依存)
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` VARCHAR(255) NOT NULL UNIQUE,
    `title` VARCHAR(255) NOT NULL,
    `release_date` DATE,
    `maker_name` VARCHAR(255),
    `genre` VARCHAR(255),
    `url` VARCHAR(2048),
    `image_url` VARCHAR(2048),
    `source_api` VARCHAR(50) NOT NULL,
    `row_api_data_id` INT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`row_api_data_id`) REFERENCES `raw_api_data`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- users テーブルが存在しない場合に作成
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` VARCHAR(50) DEFAULT 'user',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- adminユーザーが存在しない場合のみ挿入
INSERT IGNORE INTO `users` (`username`, `email`, `password_hash`, `role`) VALUES
('admin', 'admin@tipers.live', '$2y$10$t30zU6y/L2Yd6lXv9u/kQ.W1kQ.W1kQ.W1kQ.W1kQ.W1kQ.W1kQ.W1kQ.W1kQ.W1kQ.W1kQ', 'admin');
-- パスワードは 'password' です。本番環境では必ず安全なパスワードに変更してください。
