SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- 既存のデータベース 'tiper' を使用
USE `tiper` ;

-- -----------------------------------------------------
-- Table `tiper`.`row_api_data`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `tiper`.`row_api_data` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `source_name` VARCHAR(50) NOT NULL,
  `api_product_id` VARCHAR(255) NOT NULL,
  `row_json_data` JSON NOT NULL,
  `fetched_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `tiper`.`categories`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `tiper`.`categories` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
  `type` ENUM('category', 'genre', 'label', 'director') NOT NULL,
  PRIMARY KEY (`id`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `tiper`.`products`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `tiper`.`products` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `release_date` DATE NOT NULL,
  `row_api_data_id` INT NOT NULL, -- 重複していた row_api_data_id1 を削除し、こちらを使用
  `source_api` VARCHAR(50) NOT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP(),
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  UNIQUE INDEX `row_api_data_id_UNIQUE` (`row_api_data_id` ASC) VISIBLE,
  CONSTRAINT `fk_products_row_api_data`
    FOREIGN KEY (`row_api_data_id`) -- 外部キーも修正後のカラム名に
    REFERENCES `tiper`.`row_api_data` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `tiper`.`product_categories`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `tiper`.`product_categories` (
  `product_id` INT NOT NULL,
  `category_id` INT NOT NULL,
  PRIMARY KEY (`product_id`, `category_id`), -- 重複していた products_id と categories_id を削除し、複合主キーを修正
  INDEX `fk_product_categories_products1_idx` (`product_id` ASC) VISIBLE,
  INDEX `fk_product_categories_categories1_idx` (`category_id` ASC) VISIBLE,
  CONSTRAINT `fk_product_categories_products` -- CONSTRAINT名を修正
    FOREIGN KEY (`product_id`)
    REFERENCES `tiper`.`products` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_product_categories_categories` -- CONSTRAINT名を修正
    FOREIGN KEY (`category_id`)
    REFERENCES `tiper`.`categories` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `tiper`.`users`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `tiper`.`users` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(45) NULL,
  `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_2fa_enabled` TINYINT(1) NULL DEFAULT 0,
  `2fa_code` INT NULL, -- 将2fa_code设置为NULL
  `2fa_code_expires_at` DATETIME NULL, -- 将2fa_code_expires_at设置为NULL
  PRIMARY KEY (`id`),
  UNIQUE INDEX `username_UNIQUE` (`username` ASC) VISIBLE,
  UNIQUE INDEX `email_UNIQUE` (`email` ASC) VISIBLE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `tiper`.`sessions`
-- -----------------------------------------------------
-- PHPのカスタムセッションハンドラに合わせて定義を修正
CREATE TABLE IF NOT EXISTS `tiper`.`sessions` (
  `session_id` VARCHAR(255) NOT NULL PRIMARY KEY, -- PHPセッションハンドラが使用するID
  `user_id` INT NULL,                               -- ログインユーザーID (NULL許容)
  `data` LONGTEXT NOT NULL,                         -- セッションデータ
  `expires_at` INT UNSIGNED NOT NULL,             -- 有効期限 (Unixタイムスタンプ)
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
  INDEX `fk_sessions_users1_idx` (`user_id` ASC) VISIBLE, -- usersテーブルへの外部キー
  CONSTRAINT `fk_sessions_users1`
    FOREIGN KEY (`user_id`)
    REFERENCES `tiper`.`users` (`id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `tiper`.`media`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `tiper`.`media` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `entity_type` VARCHAR(45) NOT NULL,
  `entity_id` INT NOT NULL,
  `file_name` VARCHAR(255) NULL,
  `file_path` VARCHAR(500) NULL,
  `mime_type` VARCHAR(100) NULL,
  `file_size` INT NULL,
  `alt_text` VARCHAR(255) NOT NULL,
  `is_primary` TINYINT(1) NULL DEFAULT 0,
  `sort_order` INT NULL DEFAULT 0,
  `uploaded_by_user_id` INT NOT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP(),
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
  `users_id` INT NOT NULL,
  `product_id` INT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_media_users1_idx` (`users_id` ASC) VISIBLE,
  INDEX `fk_media_products1_idx` (`product_id` ASC) VISIBLE, -- product_id をインデックスに追加
  CONSTRAINT `fk_media_users`
    FOREIGN KEY (`users_id`)
    REFERENCES `tiper`.`users` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_media_products`
    FOREIGN KEY (`product_id`)
    REFERENCES `tiper`.`products` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=1;
SET UNIQUE_CHECKS=1;
