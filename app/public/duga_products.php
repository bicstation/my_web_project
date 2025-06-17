<?php
// C:\project\my_web_project\app\public\duga_products.php
// Duga商品の一覧ページ (index.phpにインクルードされることを想定)

// LoggerとDatabaseクラスは親スクリプト (index.php) でuseされている、
// もしくはオートロードされることを前提とする
use App\Core\Logger;
use App\Core\Database;

global $pdo, $logger; // index.php で設定された$pdoと$loggerを利用

// VS Codeの型推論を助けるためのPHPDocを追加
/** @var \App\Core\Logger $logger */
/** @var \PDO $pdo */

$products = [];
$errorMessage = '';
$currentPage = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$itemsPerPage = 24; // 1ページあたりの表示件数
$offset = ($currentPage - 1) * $itemsPerPage;
$totalProducts = 0;
$totalPages = 1;

// フィルタリングとソートのパラメータを取得
$selectedGenre = $_GET['genre'] ?? '';
$sortBy = $_GET['sort_by'] ?? 'release_date'; // デフォルトは発売日でソート
$sortOrder = $_GET['order'] ?? 'DESC'; // デフォルトは降順

// ソート順序のバリデーション
if (!in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
    $sortOrder = 'DESC';
}
// ソートカラムのバリデーション
if (!in_array($sortBy, ['release_date', 'title', 'maker_name'])) {
    $sortBy = 'release_date';
}

if ($logger) {
    $logger->log("Duga商品一覧ページ: フィルタリング: ジャンル='{$selectedGenre}', ソート: カラム='{$sortBy}', 順序='{$sortOrder}', ページ='{$currentPage}'");
}

try {
    if (!$pdo) {
        // $pdoがまだセットされていない場合は、ここでデータベース接続を確立
        $dbConfig = [
            'host'    => $_ENV['DB_HOST'] ?? 'localhost',
            'dbname'  => $_ENV['DB_NAME'] ?? 'web_project_db',
            'user'    => $_ENV['DB_USER'] ?? 'root',
            'pass'    => $_ENV['DB_PASS'] ?? 'password',
            'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
        ];
        $database = new Database($dbConfig, $logger);
        $pdo = $database->getConnection();
        if ($logger) {
            $logger->log("Duga商品一覧ページ: PDOが未設定だったため、再接続を試みました。");
        }
    }

    // 全商品数を取得 (ページネーション用)
    $countSql = "SELECT COUNT(*) FROM products WHERE source_api = 'Duga'";
    $dataSql = "SELECT product_id, title, release_date, maker_name, genre, url, image_url FROM products WHERE source_api = 'Duga'";
    $params = [];

    if (!empty($selectedGenre)) {
        $countSql .= " AND genre = :genre";
        $dataSql .= " AND genre = :genre";
        $params[':genre'] = $selectedGenre;
    }

    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalProducts = $countStmt->fetchColumn();
    $totalPages = ceil($totalProducts / $itemsPerPage);

    // 商品データを取得
    $dataSql .= " ORDER BY {$sortBy} {$sortOrder} LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($dataSql);

    // LIMITとOFFSETは整数としてバインドする
    // PDO::PARAM_INT は bindValue でのみ有効。executeの配列には直接値を渡す。
    $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($logger) {
        $logger->log("Duga商品一覧ページ: " . count($products) . " 件の商品を取得しました。合計商品数: {$totalProducts}, 総ページ数: {$totalPages}");
    }

    // 利用可能なジャンルを動的に取得
    $genreStmt = $pdo->query("SELECT DISTINCT genre FROM products WHERE source_api = 'Duga' AND genre IS NOT NULL AND genre != '' ORDER BY genre ASC");
    $availableGenres = $genreStmt->fetchAll(PDO::FETCH_COLUMN);
    if ($logger) {
        $logger->log("Duga商品一覧ページ: 利用可能なジャンル: " . json_encode($availableGenres));
    }

} catch (PDOException $e) {
    $errorMessage = "データベースエラーが発生しました: " . htmlspecialchars($e->getMessage());
    error_log("Duga Product List DB error: " . $e->getMessage());
    if ($logger) {
        $logger->error("Duga Product List DB error: " . $e->getMessage());
    }
} catch (Exception $e) {
    $errorMessage = "アプリケーションエラーが発生しました: " . htmlspecialchars($e->getMessage());
    error_log("Duga Product List application error: " . $e->getMessage());
    if ($logger) {
        $logger->error("Duga Product List application error: " . $e->getMessage());
    }
}
?>

<style>
    /* 一覧ページ用のスタイル */
    .product-list-card {
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        overflow: hidden;
        height: 100%; /* 親要素の高さに合わせる */
        display: flex;
        flex-direction: column;
        background-color: #fff;
    }
    .product-list-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
    }
    .product-list-card img {
        height: 180px; /* 固定の高さ */
        object-fit: cover; /* 画像をカバーするように表示 */
        width: 100%;
        border-bottom: 1px solid #f0f0f0;
    }
    .product-list-card .card-body {
        padding: 15px;
        flex-grow: 1; /* 残りのスペースを埋める */
        display: flex;
        flex-direction: column;
    }
    .product-list-card .card-title {
        font-size: 1.1rem;
        font-weight: bold;
        color: #343a40;
        margin-bottom: 8px;
        line-height: 1.4;
        min-height: 40px; /* タイトル行数を揃えるために最小高さを設定 */
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 2; /* 2行で省略 */
        -webkit-box-orient: vertical;
    }
    .product-list-card .card-text {
        font-size: 0.9rem;
        color: #6c757d;
        margin-bottom: 5px;
    }
    .product-list-card .btn-outline-primary {
        margin-top: auto; /* ボタンを一番下に配置 */
        width: 100%;
        border-radius: 5px;
        font-weight: 600;
    }
    .product-list-card .btn-outline-primary:hover {
        color: #fff;
    }
    .filter-sort-form {
        background-color: #f8f9fa;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 30px;
    }
    .filter-sort-form .form-label {
        font-weight: bold;
        color: #495057;
    }
    .filter-sort-form .form-select, .filter-sort-form .form-control {
        border-radius: 8px;
    }
    .pagination-nav {
        margin-top: 30px;
        margin-bottom: 30px;
        display: flex;
        justify-content: center;
    }
    .pagination-nav .pagination .page-item .page-link {
        border-radius: 8px;
        margin: 0 3px;
        border: 1px solid #dee2e6;
        color: #007bff;
        transition: all 0.2s ease;
    }
    .pagination-nav .pagination .page-item.active .page-link {
        background-color: #007bff;
        border-color: #007bff;
        color: #fff;
        box-shadow: 0 2px 8px rgba(0,123,255,0.2);
    }
    .pagination-nav .pagination .page-item .page-link:hover {
        background-color: #e9ecef;
        border-color: #dee2e6;
    }
    .pagination-nav .pagination .page-item.disabled .page-link {
        color: #6c757d;
    }
</style>

<div class="container-fluid bg-white p-4 rounded shadow-sm">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb bg-light p-3 rounded shadow-sm">
            <li class="breadcrumb-item"><a href="/"><i class="fas fa-home me-1"></i>ホーム</a></li>
            <li class="breadcrumb-item active" aria-current="page"><i class="fas fa-video me-1"></i>Duga商品一覧</li>
        </ol>
    </nav>

    <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-danger mt-3" role="alert">
            <?= $errorMessage ?>
        </div>
    <?php endif; ?>

    <div class="filter-sort-form">
        <form action="index.php" method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="duga_products_page">
            <div class="col-md-4">
                <label for="genre-select" class="form-label">ジャンルで絞り込み:</label>
                <select class="form-select" id="genre-select" name="genre">
                    <option value="">全てのジャンル</option>
                    <?php foreach ($availableGenres as $genre): ?>
                        <option value="<?= htmlspecialchars($genre) ?>" <?= ($selectedGenre === $genre) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($genre) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="sort-by-select" class="form-label">並び順:</label>
                <select class="form-select" id="sort-by-select" name="sort_by">
                    <option value="release_date" <?= ($sortBy === 'release_date') ? 'selected' : '' ?>>発売日</option>
                    <option value="title" <?= ($sortBy === 'title') ? 'selected' : '' ?>>タイトル</option>
                    <option value="maker_name" <?= ($sortBy === 'maker_name') ? 'selected' : '' ?>>メーカー</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="sort-order-select" class="form-label">昇順/降順:</label>
                <select class="form-select" id="sort-order-select" name="order">
                    <option value="DESC" <?= ($sortOrder === 'DESC') ? 'selected' : '' ?>>降順 (新しい順/Z-A)</option>
                    <option value="ASC" <?= ($sortOrder === 'ASC') ? 'selected' : '' ?>>昇順 (古い順/A-Z)</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-2"></i>適用</button>
            </div>
        </form>
    </div>

    <?php if ($totalProducts > 0): ?>
        <h3 class="mb-4 text-center">Duga商品一覧 (<?= htmlspecialchars($totalProducts) ?> 件)</h3>
        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-4">
            <?php foreach ($products as $product): ?>
                <div class="col">
                    <div class="card product-list-card">
                        <img src="<?= htmlspecialchars($product['image_url'] ?? 'https://placehold.co/300x180/e9ecef/6c757d?text=No Image') ?>" class="card-img-top" alt="<?= htmlspecialchars($product['title'] ?? 'タイトル不明') ?>" onerror="this.onerror=null;this.src='https://placehold.co/300x180/e9ecef/6c757d?text=No Image';">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($product['title'] ?? 'タイトル不明') ?></h5>
                            <p class="card-text"><i class="fas fa-industry me-1"></i><?= htmlspecialchars($product['maker_name'] ?? '不明') ?></p>
                            <p class="card-text"><i class="fas fa-calendar-alt me-1"></i><?= htmlspecialchars($product['release_date'] ?? '不明') ?></p>
                            <p class="card-text"><i class="fas fa-tag me-1"></i><?= htmlspecialchars($product['genre'] ?? '不明') ?></p>
                            <a href="http://<?= htmlspecialchars($_SERVER['HTTP_HOST']) ?>/index.php?page=duga_product_detail&product_id=<?= urlencode($product['product_id']) ?>" class="btn btn-outline-primary mt-3">詳細を見る <i class="fas fa-chevron-right ms-1"></i></a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <nav aria-label="Page navigation" class="pagination-nav">
            <ul class="pagination">
                <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=duga_products_page&page_num=<?= $currentPage - 1 ?>&genre=<?= urlencode($selectedGenre) ?>&sort_by=<?= urlencode($sortBy) ?>&order=<?= urlencode($sortOrder) ?>" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= ($currentPage === $i) ? 'active' : '' ?>">
                        <a class="page-link" href="?page=duga_products_page&page_num=<?= $i ?>&genre=<?= urlencode($selectedGenre) ?>&sort_by=<?= urlencode($sortBy) ?>&order=<?= urlencode($sortOrder) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=duga_products_page&page_num=<?= $currentPage + 1 ?>&genre=<?= urlencode($selectedGenre) ?>&sort_by=<?= urlencode($sortBy) ?>&order=<?= urlencode($sortOrder) ?>" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>
    <?php else: ?>
        <div class="alert alert-info mt-3 text-center" role="alert">
            条件に一致するDuga商品が見つかりませんでした。
            <?php if (!empty($selectedGenre)): ?>
                <a href="http://<?= htmlspecialchars($_SERVER['HTTP_HOST']) ?>/index.php?page=duga_products_page" class="btn btn-sm btn-primary ms-3">全てのジャンルを表示</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>