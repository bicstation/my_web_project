-- C:\project\my_web_project\database\schema.sql

-- データベースを削除し、再作成 (開発時のみ安全に行う)
DROP DATABASE IF EXISTS tiper;
CREATE DATABASE tiper;
USE tiper;

-- users テーブル
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_admin BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- sessions テーブル (カスタムセッションハンドラ用)
CREATE TABLE sessions (
    session_id VARCHAR(255) PRIMARY KEY,
    data TEXT NOT NULL,
    expires_at INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- row_api_data テーブル (生のAPIデータ保存用)
CREATE TABLE IF NOT EXISTS `tiper`.`row_api_data` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `source_name` VARCHAR(50) NOT NULL,
  `api_product_id` VARCHAR(255) NOT NULL, -- Dugaのcontent_idなどを格納
  `row_json_data` JSON NOT NULL,
  `fetched_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  UNIQUE INDEX `uq_source_api_product_id` (`source_name` ASC, `api_product_id` ASC) VISIBLE) -- ★追加: ユニークインデックス
ENGINE = InnoDB;


-- products テーブル (APIデータを整形した商品データ保存用)
CREATE TABLE IF NOT EXISTS `tiper`.`products` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `product_id` VARCHAR(255) UNIQUE NOT NULL, -- Dugaのcontent_idなど、API提供元のユニークID
  `title` VARCHAR(512) NOT NULL,
  `release_date` DATE,
  `maker_name` VARCHAR(255),
  `genre` JSON, -- ジャンルが配列の場合、JSON型で保存
  `url` TEXT,
  `image_url` TEXT,
  `row_api_data_id` INT NOT NULL, -- row_api_dataテーブルへの外部キー
  `source_api` VARCHAR(50) NOT NULL, -- 'duga', 'fanza' など
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `uq_product_id` (`product_id` ASC) VISIBLE, -- product_idのユニーク制約
  UNIQUE INDEX `row_api_data_id_UNIQUE` (`row_api_data_id` ASC) VISIBLE, -- row_api_data_idのユニーク制約
  CONSTRAINT `fk_products_row_api_data`
    FOREIGN KEY (`row_api_data_id`)
    REFERENCES `tiper`.`row_api_data` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;

-- categories テーブル (ジャンル、カテゴリ、レーベル、監督などのマスタデータ)
CREATE TABLE IF NOT EXISTS `tiper`.`categories` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL UNIQUE, -- 名前もユニークにすると良い
  `type` ENUM('category', 'genre', 'label', 'director', 'actor') NOT NULL, -- actorも追加
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`))
ENGINE = InnoDB;

-- product_categories テーブル (productsとcategoriesの中間テーブル)
CREATE TABLE IF NOT EXISTS `tiper`.`product_categories` (
  `product_id` INT NOT NULL, -- 修正: NOT NOT NULL から NOT NULL に変更
  `category_id` INT NOT NULL,
  PRIMARY KEY (`product_id`, `category_id`),
  INDEX `fk_product_categories_products_idx` (`product_id` ASC) VISIBLE,
  INDEX `fk_product_categories_categories_idx` (`category_id` ASC) VISIBLE,
  CONSTRAINT `fk_product_categories_products`
    FOREIGN KEY (`product_id`)
    REFERENCES `tiper`.`products` (`id`)
    ON DELETE CASCADE -- 商品削除時に中間テーブルの関連も削除
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_product_categories_categories`
    FOREIGN KEY (`category_id`)
    REFERENCES `tiper`.`categories` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- media テーブル (画像などのメディアファイル情報)
CREATE TABLE IF NOT EXISTS `tiper`.`media` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `entity_type` VARCHAR(45) NOT NULL,    -- 例: 'product', 'user'
  `entity_id` INT NOT NULL,              -- 関連するエンティティのID (products.id, users.id など)
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,     -- ファイルのストレージパスまたはURL
  `mime_type` VARCHAR(100) NULL,
  `file_size` INT NULL,                  -- バイト単位
  `alt_text` VARCHAR(255) NULL,          -- 代替テキスト
  `is_primary` TINYINT(1) NULL DEFAULT 0, -- プライマリ画像か
  `sort_order` INT NULL DEFAULT 0,
  `uploaded_by_user_id` INT NULL,        -- アップロードしたユーザー (users.id)
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  INDEX `idx_entity` (`entity_type` ASC, `entity_id` ASC) VISIBLE,
  INDEX `fk_media_uploaded_by_user_idx` (`uploaded_by_user_id` ASC) VISIBLE,
  CONSTRAINT `fk_media_uploaded_by_user`
    FOREIGN KEY (`uploaded_by_user_id`)
    REFERENCES `tiper`.`users` (`id`)
    ON DELETE SET NULL -- ユーザー削除時にNULLにする
    ON UPDATE NO ACTION);

-- その他のテーブル定義がここに追加される可能性があります (例: orders, reviewsなど)

-- SQL_MODE、FOREIGN_KEY_CHECKS、UNIQUE_CHECKS の設定を元に戻す
-- SET SQL_MODE=@OLD_SQL_MODE; -- この行を削除
SET FOREIGN_KEY_CHECKS=1;
SET UNIQUE_CHECKS=1;
