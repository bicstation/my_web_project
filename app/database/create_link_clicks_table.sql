-- C:\project\my_web_project\app\database\create_link_clicks_table.sql

-- ★修正: 使用するデータベースを明示的に指定 (エラーメッセージ 'tiper.link_clicks' に合わせて 'tiper' に変更)
USE tiper; 

-- link_clicks テーブルが存在しない場合に作成
CREATE TABLE IF NOT EXISTS `link_clicks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` VARCHAR(255) NOT NULL,
    `click_type` VARCHAR(50) NOT NULL, -- 例: 'affiliate_purchase', 'detail_view', 'banner_click' など
    `referrer` VARCHAR(2048),          -- 参照元URL
    `user_agent` VARCHAR(512),         -- ユーザーエージェント文字列
    `ip_address` VARCHAR(45),          -- クリック元のIPアドレス (IPv4で15文字、IPv6で45文字まで)
    `clicked_at` DATETIME DEFAULT CURRENT_TIMESTAMP, -- クリック日時
    INDEX `idx_product_id` (`product_id`),
    INDEX `idx_clicked_at` (`clicked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ★追加: sessions テーブルが存在しない場合に作成
CREATE TABLE IF NOT EXISTS `sessions` (
    `session_id` VARCHAR(255) NOT NULL PRIMARY KEY,
    `data` MEDIUMTEXT, -- セッションデータ
    `expires_at` INT UNSIGNED NOT NULL, -- UNIXタイムスタンプで有効期限を保存
    INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
