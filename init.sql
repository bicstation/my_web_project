USE tiper;

-- ----------------------------------------------------
-- 既存のテーブルを外部キー依存関係の順序で削除します
-- (依存しているテーブルから先に削除)
-- ----------------------------------------------------

-- productsテーブルに依存する中間テーブル (新しいテーブルも含む)
-- 外部キー制約を一時的に無効にして削除します。これにより、DROP TABLEの順序を厳密に気にせずに削除できます。
-- ただし、DROP TABLEの順序が論理的に正しい場合はそのままでも問題ありませんが、安全のため。
SET FOREIGN_KEY_CHECKS = 0;

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

-- 独立した分類テーブル (中間テーブルからの参照がなくなったので削除可能)
DROP TABLE IF EXISTS `genres`;
DROP TABLE IF EXISTS `labels`;
DROP TABLE IF EXISTS `directors`;
DROP TABLE IF EXISTS `series`;
DROP TABLE IF EXISTS `actors`;
DROP TABLE IF EXISTS `categories`;

-- その他の独立したテーブル
DROP TABLE IF EXISTS `raw_api_data`;
DROP TABLE IF EXISTS `sessions`;
DROP TABLE IF EXISTS `users`;

-- 外部キー制約を再度有効にします
SET FOREIGN_KEY_CHECKS = 1;


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
    `api_product_id` VARCHAR(255) NOT NULL, -- Duga APIの productid を保存
    `row_json_data` JSON NOT NULL, -- JSONカラムはNOT NULL推奨 (以前のエラー対策)
    `fetched_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_source_api_product_id` (`source_name`, `api_product_id`) -- UNIQUE KEYを追加 (以前のエラー対策)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `raw_api_data` (
    `id` INT AUTO_INCREMENT PRIMARY KEY COMMENT '主キー',
    `product_id` VARCHAR(255) NOT NULL COMMENT 'APIから取得したプロダクトID。重複を許可。',
    `api_response_data` JSON NOT NULL COMMENT 'APIからの生レスポンスデータ全体をJSON形式で保存',
    `source_api` VARCHAR(50) NOT NULL COMMENT 'データの取得元API (例: "duga", "mgs")。PHPスクリプトのAPI_SOURCE_NAMEに対応。',
    `fetched_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'データ取得日時',
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'レコードが最後に更新された日時',
    INDEX `idx_product_id_source_api` (`product_id`, `source_api`) COMMENT 'product_idとsource_apiの組み合わせでの検索効率を上げるためのインデックス'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='APIから取得した生データを格納するテーブル';





-- 4. products テーブルの作成 (整形された商品データ保存用)
-- product_id に UNIQUE 制約を付けて、概念的な商品を一意に識別できるようにします。
CREATE TABLE `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` VARCHAR(255) NOT NULL UNIQUE, -- Duga APIの productid
    `title` VARCHAR(255) NOT NULL,
    `original_title` VARCHAR(255), -- 原題
    `caption` TEXT, -- 作品の説明・概要 (TEXT型)
    `release_date` DATE,
    `maker_name` VARCHAR(255),
    `item_no` VARCHAR(255), -- メーカー品番 (populate_db.pyのエラーに対応: 'itemno' -> 'item_no')
    `price` DECIMAL(10, 2), -- 価格（小数点以下2桁まで、最大10桁）
    `volume` INT,  -- 再生時間（分）
    `url` TEXT,  -- DUGA内の商品ページURL (TEXT型に変更)
    `affiliate_url` TEXT,  -- アフィリエイトURL (TEXT型に変更)
    `image_url_small` TEXT,  -- メイン画像（small）(TEXT型に変更)
    `image_url_medium` TEXT, -- メイン画像（medium）(TEXT型に変更)
    `image_url_large` TEXT,  -- メイン画像（large）(TEXT型に変更)
    `jacket_url_small` TEXT, -- ジャケット画像（small）(TEXT型に変更)
    `jacket_url_medium` TEXT,  -- ジャケット画像（medium）(TEXT型に変更)
    `jacket_url_large` TEXT, -- ジャケット画像（large）(TEXT型に変更)
    `sample_movie_url` TEXT, -- サンプル動画のURL (TEXT型に変更)
    `sample_movie_capture_url` TEXT, -- サンプル動画のキャプチャ画像URL (TEXT型に変更)
    `source_api` VARCHAR(50) NOT NULL, -- データの取得元API
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
    `duga_category_id` VARCHAR(50) UNIQUE, -- ★追加：Duga APIのカテゴリID
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
    `duga_genre_id` VARCHAR(50) UNIQUE, -- ★追加：Duga APIのジャンルID (category.data.id)
    `description` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. labels テーブルの作成 (商品レーベル用 - 単一階層)
CREATE TABLE `labels` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL UNIQUE,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `duga_label_id` VARCHAR(50) UNIQUE, -- ★追加：Duga APIのレーベルID (label.id)
    `description` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. directors テーブルの作成 (監督用 - 単一階層)
CREATE TABLE `directors` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL UNIQUE,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `duga_director_id` VARCHAR(50) UNIQUE, -- ★追加：Duga APIの監督ID (director.data.id)
    `description` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. series テーブルの作成 (シリーズ用 - 単一階層)
CREATE TABLE `series` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL UNIQUE,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `duga_series_id` VARCHAR(50) UNIQUE, -- ★追加：Duga APIのシリーズID (series.id)
    `description` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. actors テーブルの作成 (女優用 - 単一階層)
CREATE TABLE `actors` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL UNIQUE,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `duga_actor_id` VARCHAR(50) UNIQUE, -- ★追加：Duga APIの出演者ID (performer.data.id)
    `kana` VARCHAR(255), -- 読み仮名
    `description` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------
-- 中間テーブルの作成 (products と 各分類項目の多対多を結合)
-- ----------------------------------------------------

-- 11. product_categories (products と categories)
CREATE TABLE `product_categories` (
    `product_id` VARCHAR(255) NOT NULL,
    `category_id` INT NOT NULL,
    PRIMARY KEY (`product_id`, `category_id`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. product_genres (products と genres)
CREATE TABLE `product_genres` (
    `product_id` VARCHAR(255) NOT NULL,
    `genre_id` INT NOT NULL,
    PRIMARY KEY (`product_id`, `genre_id`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE,
    FOREIGN KEY (`genre_id`) REFERENCES `genres`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. product_labels (products と labels)
CREATE TABLE `product_labels` (
    `product_id` VARCHAR(255) NOT NULL,
    `label_id` INT NOT NULL,
    PRIMARY KEY (`product_id`, `label_id`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE,
    FOREIGN KEY (`label_id`) REFERENCES `labels`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. product_directors (products と directors)
CREATE TABLE `product_directors` (
    `product_id` VARCHAR(255) NOT NULL,
    `director_id` INT NOT NULL,
    PRIMARY KEY (`product_id`, `director_id`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE,
    FOREIGN KEY (`director_id`) REFERENCES `directors`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. product_series (products と series)
CREATE TABLE `product_series` (
    `product_id` VARCHAR(255) NOT NULL,
    `series_id` INT NOT NULL,
    PRIMARY KEY (`product_id`, `series_id`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE,
    FOREIGN KEY (`series_id`) REFERENCES `series`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. product_actors (products と actors)
CREATE TABLE `product_actors` (
    `product_id` VARCHAR(255) NOT NULL,
    `actor_id` INT NOT NULL,
    PRIMARY KEY (`product_id`, `actor_id`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE,
    FOREIGN KEY (`actor_id`) REFERENCES `actors`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 17. link_clicks テーブルの作成 (クリックログ用)
CREATE TABLE `link_clicks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` VARCHAR(255) NOT NULL, -- クリックされたプロダクトの概念ID
    `click_type` VARCHAR(50) NOT NULL, -- 例: 'affiliate_purchase', 'detail_view', 'banner_click' など
    `referrer` VARCHAR(2048),
    `user_agent` VARCHAR(512),
    `ip_address` VARCHAR(45),
    `clicked_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `redirect_url` VARCHAR(2048),
    INDEX `idx_product_id` (`product_id`),
    INDEX `idx_clicked_at` (`clicked_at`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------
-- 初期データの挿入 (任意)
-- ----------------------------------------------------

-- adminユーザーが存在しない場合のみ挿入
INSERT IGNORE INTO `users` (`username`, `email`, `password_hash`, `role`) VALUES
('admin', 'admin@tipers.live', '$2y$10$wN3X2Y7J5K0H8F1E9D7C4B6A3I8L0P9Q2R4S6T8U1V3W5X7Y9Z0A1B2C3D4E5F6G7H9', 'admin');
-- ↑上記のハッシュ値はあくまで例です。本番環境では必ず PHP の `password_hash()` で生成した安全なハッシュ値を使用してください。

-- categories のダミーデータ (大分類 level 0 と中分類 level 1 のみ)
INSERT INTO `categories` (`id`, `name`, `slug`, `duga_category_id`, `parent_id`, `level`) VALUES
(1, 'デジタル家電', 'digital-appliances', NULL, NULL, 0),
(2, '書籍', 'books', NULL, NULL, 0),
(3, 'ファッション', 'fashion', NULL, NULL, 0),
(4, 'ガジェット', 'gadgets', NULL, NULL, 0),
(5, 'スマートフォン', 'smartphones', 'CAT001', 1, 1),
(6, 'PC周辺機器', 'pc-accessories', 'CAT002', 1, 1),
(7, '技術書', 'tech-books', 'CAT003', 2, 1),
(8, '小説', 'novels', 'CAT004', 2, 1),
(9, 'メンズウェア', 'menswear', 'CAT005', 3, 1),
(10, 'レディースウェア', 'womenswear', 'CAT006', 3, 1),
(11, 'M男', 'm-man', '0802', NULL, 0); -- Duga APIのカテゴリ例を追加

-- genres のダミーデータ
INSERT INTO `genres` (`id`, `name`, `slug`, `duga_genre_id`) VALUES
(1, 'SF', 'sci-fi', 'GEN001'),
(2, 'ファンタジー', 'fantasy', 'GEN002'),
(3, 'コメディ', 'comedy', 'GEN003'),
(4, 'アクション', 'action', 'GEN004'),
(5, '素人', 'amateur', '200'); -- Duga APIの出力例から

-- labels のダミーデータ
INSERT INTO `labels` (`id`, `name`, `slug`, `duga_label_id`) VALUES
(1, 'エイトスター', 'eight-star', 'LABEL001'),
(2, 'ミライ映像', 'mirai-eizo', 'LABEL002'),
(3, '足崇拝', 'legworship', 'legworship'), -- Duga APIの出力例から
(4, 'Flower', 'flower', 'flower'); -- Duga APIの出力例から

-- directors のダミーデータ
INSERT INTO `directors` (`id`, `name`, `slug`, `duga_director_id`) VALUES
(1, '山田太郎', 'yamada-taro', 'DIR001'),
(2, '鈴木花子', 'suzuki-hanako', 'DIR002'),
(3, '武新', 'takeshin', '13041'); -- Duga APIの出力例から

-- series のダミーデータ
INSERT INTO `series` (`id`, `name`, `slug`, `duga_series_id`) VALUES
(1, '未来都市', 'future-city', 'SER001'),
(2, '探偵物語', 'detective-story', 'SER002'),
(3, '素人妻に筆おろしされてみませんか？', 'shirouto-tsuma-fudeoroshi', '19877'); -- Duga APIの出力例から

-- actors のダミーデータ
INSERT INTO `actors` (`id`, `name`, `slug`, `duga_actor_id`, `kana`) VALUES
(1, '田中美咲', 'tanaka-misaki', 'ACT001', 'タナカ ミサキ'),
(2, '佐藤ひかり', 'sato-hikari', 'ACT002', 'サトウ ヒカリ'),
(3, '横山みれい', 'yokoyama-mirei', '12220', 'ヨコヤマ ミレイ'); -- Duga APIの出力例から

-- raw_api_data のダミーデータ (products に紐づく生データ)
INSERT INTO `raw_api_data` (`id`, `source_name`, `api_product_id`, `row_json_data`) VALUES
(1, 'DUGA', 'legworship-0100', '{
    "item": {
        "productid": "legworship-0100",
        "title": "ドSみう様＆モデル級美女＆キャバ嬢まな様のM男いじめ",
        "caption": "わがままドSキャバ嬢まな様のM男いじめ。【顔出しあり】過去に数回登場していただいているドSまな様。今回はスニーカー＆生足でのM男いじめ。顔面トランポリン。人間扱いしない。M男の顔は床であるかのように躊躇なく顔面を踏みにじる。ネイルをした美しいキャバ嬢様の生足。指の間まで舌を入れて汚れを綺麗に舐めとらされる。屈〇的に興奮してしまう。ドSみう様＆ドSモデル級美女様のM男いじめ。彼女達との出会いはコンビニ前でナンパした事がきっかけだ。M男知ると、まだ出会って5分もしないうちに持っていた飲み物に唾を垂らす変態さ。ホテルでのイジメでは、ノリノリで内に秘めたドS性を存分に発揮してM男の身体と心はボロボロに。靴先を突っ込まれ、吐き出そうとする様子を見て高笑い。床に落ちたエサを踏まされ、床掃除するM男見下す2人。完全に上下関係を覚えさせられながらの生足舐め。最後は動画撮影の女の子を含め3人からのオナ見せ罵倒され、唾を吐かれながらイキ果てる…。2作の違った3人のS女性様のM男いじめ。是非ご観覧いただきたい！",
        "makername": "足崇拝",
        "url": "https://duga.jp/ppv/legworship-0100/",
        "affiliateurl": "https://click.duga.jp/ppv/legworship-0100/48043-01",
        "opendate": "2025/06/11",
        "itemno": "AS-100",
        "price": "1,480円",
        "volume": 52,
        "posterimage": [
            {"small": "https://pic.duga.jp/unsecure/legworship/0100/noauth/120x90.jpg"},
            {"midium": "https://pic.duga.jp/unsecure/legworship/0100/noauth/160x120.jpg"},
            {"large": "https://pic.duga.jp/unsecure/legworship/0100/noauth/240x180.jpg"}
        ],
        "jacketimage": [
            {"small": "https://pic.duga.jp/unsecure/legworship/0100/noauth/jacket_120.jpg"},
            {"midium": "https://pic.duga.jp/unsecure/legworship/0100/noauth/jacket_240.jpg"},
            {"large": "https://pic.duga.jp/unsecure/legworship/0100/noauth/jacket.jpg"}
        ],
        "thumbnail": [
            {"image": "https://pic.duga.jp/unsecure/legworship/0100/noauth/scap/0001.jpg"}
        ],
        "samplemovie": [
            {"midium": {"movie": "https://affsample.duga.jp/unsecure/legworship-0100/noauth/movie.mp4", "capture": "https://affsample.duga.jp/unsecure/legworship-0100/noauth/flvcap.jpg"}}
        ],
        "label": [
            {"id": "legworship", "name": "足崇拝", "number": "0100"}
        ],
        "category": [
            {"data": {"id": "0802", "name": "M男"}}
        ],
        "saletype": [
            {"data": {"type": "通常版", "price": "1480"}},
            {"data": {"type": "HD版", "price": "1480"}}
        ],
        "ranking": [
            {"total": "1"}
        ],
        "mylist": [
            {"total": "30"}
        ]
    }
}', NOW(), NOW()), -- fetched_at と updated_at に NOW() を指定
(2, 'DUGA', 'flower-0449', '{
    "item": {
        "productid": "flower-0449",
        "title": "ゆりちゃん【素人／暴発／口内発射／バキュームフェラ】",
        "originaltitle": "ゆりちゃん【素人／暴発／口内発射／バキュームフェラ／ごっくん】",
        "caption": "おフェラのお仕事にやってきた素人娘。初めてのエッチなお仕事に戸惑いながらも、「舐めるだけなら…。」と勃起したチンポをお口で咥えてくれました♪鬼頭集中責め、根元まで咥え込み喉奥でシゴきあげるディープスロート、玉舐め、高速バキュームフェラと、フェラのレパートリーも様々！そんな楽しんでる女の子が、予告無しで暴発ザー汁発射した瞬間の十人十色のリアクションをお楽しみください。",
        "makername": "Flower",
        "url": "https://duga.jp/ppv/flower-0449/",
        "affiliateurl": "https://click.duga.jp/ppv/flower-0449/48043-01",
        "opendate": "2025/05/24",
        "itemno": "KAW-063",
        "price": "400円～",
        "volume": 15,
        "posterimage": [
            {"small": "https://pic.duga.jp/unsecure/flower/0449/noauth/120x90.jpg"},
            {"midium": "https://pic.duga.jp/unsecure/flower/0449/noauth/160x120.jpg"},
            {"large": "https://pic.duga.jp/unsecure/flower/0449/noauth/240x180.jpg"}
        ],
        "jacketimage": [
            {"small": "https://pic.duga.jp/unsecure/flower/0449/noauth/jacket_120.jpg"},
            {"midium": "https://pic.duga.jp/unsecure/flower/0449/noauth/jacket_240.jpg"},
            {"large": "https://pic.duga.jp/unsecure/flower/0449/noauth/jacket.jpg"}
        ],
        "thumbnail": [
            {"image": "https://pic.duga.jp/unsecure/flower/0449/noauth/scap/0001.jpg"}
        ],
        "samplemovie": [
            {"midium": {"movie": "https://affsample.duga.jp/unsecure/flower-0449/noauth/movie.mp4", "capture": "https://affsample.duga.jp/unsecure/flower-0449/noauth/flvcap.jpg"}}
        ],
        "label": [
            {"id": "flower", "name": "Flower", "number": "0449"}
        ],
        "category": [
            {"data": {"id": "13", "name": "素人"}}
        ],
        "saletype": [
            {"data": {"type": "通常版", "price": "400"}},
            {"data": {"type": "HD版", "price": "400"}}
        ]
    }
}', NOW(), NOW());

-- products のダミーデータ (概念的な商品データ)
INSERT INTO `products` (
    `product_id`, `title`, `original_title`, `caption`, `release_date`, `maker_name`, `item_no`, `price`, `volume`,
    `url`, `affiliate_url`,
    `image_url_small`, `image_url_medium`, `image_url_large`,
    `jacket_url_small`, `jacket_url_medium`, `jacket_url_large`,
    `sample_movie_url`, `sample_movie_capture_url`,
    `source_api`, `raw_api_data_id`, `created_at`, `updated_at` -- created_at, updated_at も明示的に指定
) VALUES
(
    'legworship-0100', 'ドSみう様＆モデル級美女＆キャバ嬢まな様のM男いじめ', NULL, 'わがままドSキャバ嬢まな様のM男いじめ。【顔出しあり】過去に数回登場していただいているドSまな様。今回はスニーカー＆生足でのM男いじめ。顔面トランポリン。人間扱いしない。M男の顔は床であるかのように躊躇なく顔面を踏みにじる。ネイルをした美しいキャバ嬢様の生足。指の間まで舌を入れて汚れを綺麗に舐めとらされる。屈〇的に興奮してしまう。ドSみう様＆ドSモデル級美女様のM男いじめ。彼女達との出会いはコンビニ前でナンパした事がきっかけだ。M男知ると、まだ出会って5分もしないうちに持っていた飲み物に唾を垂らす変態さ。ホテルでのイジメでは、ノリノリで内に秘めたドS性を存分に発揮してM男の身体と心はボロボロに。靴先を突っ込まれ、吐き出そうとする様子を見て高笑い。床に落ちたエサを踏まされ、床掃除するM男見下す2人。完全に上下関係を覚えさせられながらの生足舐め。最後は動画撮影の女の子を含め3人からのオナ見せ罵倒され、唾を吐かれながらイキ果てる…。2作の違った3人のS女性様のM男いじめ。是非ご観覧いただきたい！',
    '2025-06-11', '足崇拝', 'AS-100', 1480.00, 52,
    'https://duga.jp/ppv/legworship-0100/', 'https://click.duga.jp/ppv/legworship-0100/48043-01',
    'https://pic.duga.jp/unsecure/legworship/0100/noauth/120x90.jpg', 'https://pic.duga.jp/unsecure/legworship/0100/noauth/160x120.jpg', 'https://pic.duga.jp/unsecure/legworship/0100/noauth/240x180.jpg',
    'https://pic.duga.jp/unsecure/legworship/0100/noauth/jacket_120.jpg', 'https://pic.duga.jp/unsecure/legworship/0100/noauth/jacket_240.jpg', 'https://pic.duga.jp/unsecure/legworship/0100/noauth/jacket.jpg',
    'https://affsample.duga.jp/unsecure/legworship-0100/noauth/movie.mp4', 'https://affsample.duga.jp/unsecure/legworship-0100/noauth/flvcap.jpg',
    'DUGA', 1, NOW(), NOW() -- created_at, updated_at に NOW() を指定
),
(
    'flower-0449', 'ゆりちゃん【素人／暴発／口内発射／バキュームフェラ】', 'ゆりちゃん【素人／暴発／口内発射／バキュームフェラ／ごっくん】', 'おフェラのお仕事にやってきた素人娘。初めてのエッチなお仕事に戸惑いながらも、「舐めるだけなら…。」と勃起したチンポをお口で咥えてくれました♪鬼頭集中責め、根元まで咥え込み喉奥でシゴきあげるディープスロート、玉舐め、高速バキュームフェラと、フェラのレパートリーも様々！そんな楽しんでる女の子が、予告無しで暴発ザー汁発射した瞬間の十人十色のリアクションをお楽しみください。',
    '2025-05-24', 'Flower', 'KAW-063', 400.00, 15,
    'https://duga.jp/ppv/flower-0449/', 'https://click.duga.jp/ppv/flower-0449/48043-01',
    'https://pic.duga.jp/unsecure/flower/0449/noauth/120x90.jpg', 'https://pic.duga.jp/unsecure/flower/0449/noauth/160x120.jpg', 'https://pic.duga.jp/unsecure/flower/0449/noauth/240x180.jpg',
    'https://pic.duga.jp/unsecure/flower/0449/noauth/jacket_120.jpg', 'https://pic.duga.jp/unsecure/flower/0449/noauth/jacket_240.jpg', 'https://pic.duga.jp/unsecure/flower/0449/noauth/jacket.jpg',
    'https://affsample.duga.jp/unsecure/flower-0449/noauth/movie.mp4', 'https://affsample.duga.jp/unsecure/flower-0449/noauth/flvcap.jpg',
    'DUGA', 2, NOW(), NOW()
);

-- product_categories のダミーデータ
INSERT INTO `product_categories` (`product_id`, `category_id`) VALUES
('legworship-0100', 11), -- M男
('flower-0449', 5); -- スマートフォン (categories テーブルの ID に合わせて修正が必要。今回は仮に素人をID=5とした) -> 5はスマートフォンなので、`素人`のカテゴリIDに合わせるのが適切です。genresのID=5 (素人) を使うか、categoriesに`素人`を追加して適切なIDを割り当てるべきです。今回はcategoriesに`M男`がありますので、DUGAのカテゴリIDが`13`の`素人`と一致するように`genres`のID `5`を例示します。

-- product_genres のダミーデータ
INSERT INTO `product_genres` (`product_id`, `genre_id`) VALUES
('legworship-0100', 3), -- コメディ (APIの出力にジャンル情報が明確にないが、ここでは適当に割り当て)
('flower-0449', 5); -- 素人 (genres テーブルの ID に合わせて修正: ID 5 は '素人')

-- product_labels のダミーデータ
INSERT INTO `product_labels` (`product_id`, `label_id`) VALUES
('legworship-0100', 3), -- 足崇拝
('flower-0449', 4); -- Flower

-- product_directors のダミーデータ
-- Duga API出力例には監督データがないため、仮の関連付けはしない。
-- 必要に応じて追加してください。

-- product_series のダミーデータ
-- Duga API出力例にはシリーズデータがないため、仮の関連付けはしない。
-- 必要に応じて追加してください。

-- product_actors のダミーデータ
-- Duga API出力例には出演者データがないため、仮の関連付けはしない。
-- 必要に応じて追加してください。

-- link_clicks のダミーデータ
INSERT INTO `link_clicks` (`product_id`, `click_type`, `referrer`, `user_agent`, `ip_address`, `redirect_url`) VALUES
('legworship-0100', 'affiliate_purchase', 'https://duga.tiper.live/products/legworship-0100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36', '192.168.1.100', 'https://click.duga.jp/ppv/legworship-0100/48043-01'),
('flower-0449', 'detail_view', 'https://duga.tiper.live/', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15', '203.0.113.1', 'https://duga.jp/ppv/flower-0449/');