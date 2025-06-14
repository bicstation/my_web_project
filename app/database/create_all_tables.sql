-- users テーブルの作成
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(255) NOT NULL UNIQUE,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` VARCHAR(50) DEFAULT 'user',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- sessions テーブルの作成 (カスタムセッションハンドラ用)
CREATE TABLE IF NOT EXISTS `sessions` (
    `session_id` VARCHAR(128) NOT NULL PRIMARY KEY,
    `data` BLOB NOT NULL,
    `expires_at` INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- raw_api_data テーブルの作成 (API生データ保存用)
CREATE TABLE IF NOT EXISTS `raw_api_data` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `source_name` VARCHAR(50) NOT NULL,
    `api_product_id` VARCHAR(255) NOT NULL,
    `row_json_data` JSON,
    `fetched_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_source_api_product_id` (`source_name`, `api_product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- products テーブルの作成 (整形された商品データ保存用)
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
