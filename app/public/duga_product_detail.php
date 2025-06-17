<?php
// public/duga_product_detail.php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use App\Core\Database;
use App\Core\Logger;
// LinkClicker クラスをインポート
use App\Util\LinkClicker;

$logger = null;
$database = null;
$product = null;
$related_items = []; // 関連商品
$message = "";

try {
    $logger = new Logger('application.log');
    $database = new Database([
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'dbname' => $_ENV['DB_NAME'] ?? 'tiper',
        'user' => $_ENV['DB_USER'] ?? 'root',
        'pass' => $_ENV['DB_PASS'] ?? 'password',
        'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
    ], $logger);
    $pdo = $database->getConnection();

    // LinkClicker のインスタンス化
    $linkClicker = new LinkClicker($database, $logger);

} catch (Exception $e) {
    error_log("アプリケーション初期設定エラー: " . $e->getMessage());
    if ($logger) {
        $logger->error("アプリケーション初期設定中に致命的なエラーが発生しました: " . htmlspecialchars($e->getMessage()));
    }
    die("<div class='alert alert-danger'>ウェブサイトの初期設定中にエラーが発生しました。ログを確認してください。<br>" . htmlspecialchars($e->getMessage()) . "</div>");
}

// -----------------------------------------------------
// 商品詳細の取得ロジック
// -----------------------------------------------------
$product_id = $_GET['product_id'] ?? null;

if (!$product_id) {
    // product_id がない場合はエラーまたは一覧ページへリダイレクト
    $message = "<div class='alert alert-danger'>商品IDが指定されていません。</div>";
    // header("Location: /"); // 必要であればリダイレクト
    // exit();
} else {
    try {
        // products テーブルから商品情報を取得
        $stmt = $pdo->prepare("SELECT p.*,
                                      GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') AS categories_name,
                                      GROUP_CONCAT(DISTINCT c.slug ORDER BY c.slug SEPARATOR ',') AS categories_slug,
                                      GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ', ') AS genres_name,
                                      GROUP_CONCAT(DISTINCT g.slug ORDER BY g.slug SEPARATOR ',') AS genres_slug,
                                      GROUP_CONCAT(DISTINCT l.name ORDER BY l.name SEPARATOR ', ') AS labels_name,
                                      GROUP_CONCAT(DISTINCT l.slug ORDER BY l.slug SEPARATOR ',') AS labels_slug,
                                      GROUP_CONCAT(DISTINCT d.name ORDER BY d.name SEPARATOR ', ') AS directors_name,
                                      GROUP_CONCAT(DISTINCT d.slug ORDER BY d.slug SEPARATOR ',') AS directors_slug,
                                      GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') AS series_name,
                                      GROUP_CONCAT(DISTINCT s.slug ORDER BY s.slug SEPARATOR ',') AS series_slug,
                                      GROUP_CONCAT(DISTINCT a.name ORDER BY a.name SEPARATOR ', ') AS actors_name,
                                      GROUP_CONCAT(DISTINCT a.slug ORDER BY a.slug SEPARATOR ',') AS actors_slug
                               FROM products p
                               LEFT JOIN product_categories pc ON p.product_id = pc.product_id
                               LEFT JOIN categories c ON pc.category_id = c.id
                               LEFT JOIN product_genres pg ON p.product_id = pg.product_id
                               LEFT JOIN genres g ON pg.genre_id = g.id
                               LEFT JOIN product_labels pl ON p.product_id = pl.product_id
                               LEFT JOIN labels l ON pl.label_id = l.id
                               LEFT JOIN product_directors pdr ON p.product_id = pdr.product_id
                               LEFT JOIN directors d ON pdr.director_id = d.id
                               LEFT JOIN product_series ps ON p.product_id = ps.product_id
                               LEFT JOIN series s ON ps.series_id = s.id
                               LEFT JOIN product_actors pa ON p.product_id = pa.product_id
                               LEFT JOIN actors a ON pa.actor_id = a.id
                               WHERE p.product_id = :product_id
                               GROUP BY p.product_id"); // GROUP_CONCATを使うためGROUP BYが必要

        $stmt->execute([':product_id' => $product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $message = "<div class='alert alert-warning'>お探しの商品が見つかりませんでした。</div>";
            $logger->warning("商品詳細が見つかりません: product_id = " . htmlspecialchars($product_id));
        } else {
            // 詳細ページ表示のクリックログを記録
            $linkClicker->logClick(
                $product_id,
                'detail_view',
                $_SERVER['HTTP_REFERER'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['REQUEST_URI'] ?? null // このページのURL自体を記録
            );

            // 関連商品の取得 (例: 同じカテゴリの商品をランダムに5件)
            // 簡略化のため、ここではproduct_idがnullでない限り関連商品を取得
            // 実際には、categories_slugなどを利用して関連商品を絞り込む
            $related_sql = "SELECT p.product_id, p.title, p.image_url, p.price
                            FROM products p ";
            $related_params = [];
            $related_where = " WHERE p.product_id != :current_product_id ";
            $related_params[':current_product_id'] = $product_id;

            // もしカテゴリ情報があるなら、同じカテゴリの商品を優先的に取得
            if (!empty($product['categories_slug'])) {
                $category_slugs_array = explode(',', $product['categories_slug']);
                // 最初のカテゴリに属する関連商品を探す例
                if (!empty($category_slugs_array[0])) {
                    $related_sql .= " JOIN product_categories pc ON p.product_id = pc.product_id
                                      JOIN categories c ON pc.category_id = c.id ";
                    $related_where .= " AND c.slug = :related_category_slug ";
                    $related_params[':related_category_slug'] = $category_slugs_array[0];
                }
            }

            $related_sql .= $related_where . " ORDER BY RAND() LIMIT 5"; // ランダムに5件

            $stmt_related = $pdo->prepare($related_sql);
            $stmt_related->execute($related_params);
            $related_items = $stmt_related->fetchAll(PDO::FETCH_ASSOC);
        }

    } catch (PDOException $e) {
        $logger->error("商品詳細または関連商品の取得中にエラーが発生しました: " . $e->getMessage());
        $message = "<div class='alert alert-danger'>商品情報の取得中にエラーが発生しました。</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $product ? htmlspecialchars($product['title']) . ' - Tiper.Live' : '商品が見つかりません - Tiper.Live' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f4f7f6; }
        .navbar { background-color: #343a40; }
        .navbar-brand, .nav-link { color: #ffffff !important; }
        .product-detail-card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            padding: 30px;
            background-color: #ffffff;
        }
        .product-image-container {
            text-align: center;
            margin-bottom: 20px;
        }
        .product-image-container img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .product-title {
            font-size: 2.2em;
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
        }
        .product-meta {
            font-size: 1.1em;
            color: #666;
            margin-bottom: 10px;
        }
        .product-price {
            font-size: 2em;
            color: #dc3545;
            font-weight: bold;
            margin-top: 20px;
            margin-bottom: 20px;
        }
        .affiliate-link-btn {
            background-color: #28a745; /* Green for affiliate link */
            color: white;
            padding: 15px 30px;
            font-size: 1.2em;
            font-weight: bold;
            border-radius: 8px;
            transition: background-color 0.3s ease;
        }
        .affiliate-link-btn:hover {
            background-color: #218838;
            color: white;
        }
        .section-header {
            font-size: 1.8em;
            font-weight: bold;
            color: #343a40;
            margin-top: 40px;
            margin-bottom: 20px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .related-product-card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s ease;
        }
        .related-product-card:hover {
            transform: translateY(-3px);
        }
        .related-product-card img {
            height: 150px;
            object-fit: cover;
            width: 100%;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }
        .related-product-card .card-title {
            font-size: 1em;
            height: 3em; /* 2行制限 */
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        .video-container {
            position: relative;
            width: 100%;
            padding-bottom: 56.25%; /* 16:9 Aspect Ratio */
            height: 0;
            overflow: hidden;
            background-color: #000; /* Black background for video area */
            border-radius: 8px;
            margin-top: 20px;
        }
        .video-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: 0;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand" href="/">Tiper.Live</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="/">ホーム</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/products_admin.php">管理画面</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <?php if (!empty($message)): ?>
        <?= $message ?>
    <?php elseif ($product): ?>
        <div class="row product-detail-card">
            <div class="col-md-6">
                <div class="product-image-container">
                    <img src="<?= htmlspecialchars($product['image_url'] ?: '/img/placeholder.png') ?>" alt="<?= htmlspecialchars($product['title']) ?>">
                </div>
                <?php if (!empty($product['video_url'])): ?>
                    <div class="video-container">
                        <?php
                            // YouTube URLを埋め込みURLに変換する関数
                            function getYoutubeEmbedUrl($url) {
                                $pattern = '/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com|youtu\.be)\/(?:watch\?v=|embed\/|v\/|)([\w-]{11})(?:(?:\?|&).*)?/';
                                preg_match($pattern, $url, $matches);
                                if (isset($matches[1])) {
                                    return 'https://www.youtube.com/embed/' . $matches[1] . '?autoplay=0&rel=0';
                                }
                                return null;
                            }
                            $embed_url = getYoutubeEmbedUrl($product['video_url']);
                        ?>
                        <?php if ($embed_url): ?>
                            <iframe src="<?= htmlspecialchars($embed_url) ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                        <?php else: ?>
                            <p class="text-danger text-center">動画のURLが無効です。</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <h1 class="product-title"><?= htmlspecialchars($product['title']) ?></h1>
                <p class="product-meta"><strong>リリース日:</strong> <?= htmlspecialchars($product['release_date']) ?></p>
                <?php if (!empty($product['maker_name'])): ?>
                    <p class="product-meta"><strong>メーカー:</strong> <?= htmlspecialchars($product['maker_name']) ?></p>
                <?php endif; ?>
                <?php if (!empty($product['categories_name'])): ?>
                    <p class="product-meta"><strong>カテゴリ:</strong>
                    <?php
                        $categories = explode(',', $product['categories_name']);
                        $category_slugs = explode(',', $product['categories_slug']);
                        $category_links = [];
                        foreach ($categories as $index => $cat_name) {
                            if (isset($category_slugs[$index])) {
                                $category_links[] = '<a href="/?category=' . htmlspecialchars($category_slugs[$index]) . '">' . htmlspecialchars(trim($cat_name)) . '</a>';
                            }
                        }
                        echo implode(', ', $category_links);
                    ?>
                    </p>
                <?php endif; ?>
                <?php if (!empty($product['genres_name'])): ?>
                    <p class="product-meta"><strong>ジャンル:</strong>
                    <?php
                        $genres = explode(',', $product['genres_name']);
                        $genre_slugs = explode(',', $product['genres_slug']);
                        $genre_links = [];
                        foreach ($genres as $index => $gen_name) {
                            if (isset($genre_slugs[$index])) {
                                $genre_links[] = '<a href="/?genre=' . htmlspecialchars($genre_slugs[$index]) . '">' . htmlspecialchars(trim($gen_name)) . '</a>';
                            }
                        }
                        echo implode(', ', $genre_links);
                    ?>
                    </p>
                <?php endif; ?>
                <?php if (!empty($product['labels_name'])): ?>
                    <p class="product-meta"><strong>レーベル:</strong> <?= htmlspecialchars($product['labels_name']) ?></p>
                <?php endif; ?>
                <?php if (!empty($product['directors_name'])): ?>
                    <p class="product-meta"><strong>監督:</strong> <?= htmlspecialchars($product['directors_name']) ?></p>
                <?php endif; ?>
                <?php if (!empty($product['series_name'])): ?>
                    <p class="product-meta"><strong>シリーズ:</strong> <?= htmlspecialchars($product['series_name']) ?></p>
                <?php endif; ?>
                <?php if (!empty($product['actors_name'])): ?>
                    <p class="product-meta"><strong>出演:</strong> <?= htmlspecialchars($product['actors_name']) ?></p>
                <?php endif; ?>

                <?php if (!empty($product['price'])): ?>
                    <p class="product-price">販売価格: &yen;<?= number_format($product['price']) ?></p>
                <?php endif; ?>

                <?php if (!empty($product['url'])): ?>
                    <a href="/click_redirect.php?product_id=<?= htmlspecialchars($product['product_id']) ?>&redirect_to=<?= urlencode($product['url']) ?>" class="btn affiliate-link-btn w-100" target="_blank">
                        <i class="fas fa-external-link-alt me-2"></i>外部サイトで購入する
                    </a>
                <?php else: ?>
                    <div class="alert alert-info mt-3">現在、この商品の購入リンクはありません。</div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($related_items)): ?>
            <h3 class="section-header">関連商品</h3>
            <div class="row mt-4">
                <?php foreach ($related_items as $r_product): ?>
                    <div class="col-6 col-md-4 col-lg-2 mb-4">
                        <div class="card related-product-card h-100">
                            <a href="/duga_product_detail.php?product_id=<?= htmlspecialchars($r_product['product_id']) ?>">
                                <img src="<?= htmlspecialchars($r_product['image_url'] ?: '/img/placeholder.png') ?>" class="card-img-top" alt="<?= htmlspecialchars($r_product['title']) ?>">
                            </a>
                            <div class="card-body text-center p-2">
                                <h6 class="card-title"><?= htmlspecialchars($r_product['title']) ?></h6>
                                <?php if (!empty($r_product['price'])): ?>
                                    <p class="card-text text-danger fw-bold">&yen;<?= number_format($r_product['price']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>