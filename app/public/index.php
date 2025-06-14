<?php
// C:\project\my_web_project\app\public\index.php

// Composerのオートローダーを読み込む
// これにより、App名前空間下のクラスや、vlucas/phpdotenvなどのComposerが管理するライブラリが自動的にロードされます。
require_once __DIR__ . '/../../vendor/autoload.php';

// Dotenvライブラリを使って.envファイルをロード (オートローダーの後に配置)
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// 共通初期化ファイルを読み込む（セッションハンドラ設定とsession_start()を含む）
// init.php がセッションを開始する前に、ここまでの出力がないことを確認することが重要。
require_once __DIR__ . '/init.php';

// 名前空間を使用するクラスをインポート
use App\Core\Logger;
use App\Core\Database;
// use PDOException; // PDOException はグローバルクラスなので不要です

// データベース接続設定を.envから取得
$dbConfig = [
    'host'    => $_ENV['DB_HOST'] ?? 'localhost',
    'dbname'  => $_ENV['DB_NAME'] ?? 'web_project_db',
    'user'    => $_ENV['DB_USER'] ?? 'root',
    'pass'    => $_ENV['DB_PASS'] ?? 'password',
    'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
];

// ロガーとデータベース接続をグローバルで利用可能にする（sidebar.phpなどからアクセスするため）
global $logger, $database, $pdo;
try {
    $logger = new Logger('main_app.log'); // <-- Loggerクラスがロードされる
    $logger->log("メインアプリケーション (index.php) へのアクセス処理を開始します。");
    $database = new Database($dbConfig, $logger);
    $pdo = $database->getConnection();
} catch (Exception $e) {
    // データベース接続エラーなどの初期化エラー
    error_log("Main application initialization error: " . $e->getMessage());
    die("サイトの初期化中にエラーが発生しました。ログを確認してください。"); // ユーザーに表示
}


// URLのクエリパラメータ 'page' を取得、またはホスト名に基づいてページを決定
$currentPage = $_GET['page'] ?? 'home';
$isDugaDomain = ($_SERVER['HTTP_HOST'] === 'duga.tipers.live');

// Dugaドメインからのアクセスであれば、ページをDuga関連ページに限定する
if ($isDugaDomain) {
    if (isset($_GET['page'])) {
        if ($_GET['page'] === 'duga_product_detail') {
            $currentPage = 'duga_product_detail';
        } else {
            // dugaドメインでpageパラメータがあるが、duga_product_detailではない場合、
            // デフォルトのduga_products_pageにフォールバック
            $currentPage = 'duga_products_page';
        }
    } else {
        // dugaドメインでpageパラメータがない場合
        $currentPage = 'duga_products_page';
    }
}

// ★追加: $currentPage の値をログに出力して確認
error_log("DEBUG: index.php - \$currentPage before switch: " . $currentPage);


// Debugging line: 現在のページとセッションのユーザーIDをPHPエラーログに出力
error_log("Current page requested: " . $currentPage . ", User ID in session: " . ($_SESSION['user_id'] ?? 'NOT SET'));


// 管理画面へのアクセスチェックとリダイレクト
// ユーザー管理ページ (users_admin) と商品登録ページ (products_admin) はログインが必要なページとして保護
$protected_pages = ['users_admin', 'products_admin']; // ここに保護したい管理ページを追加

if (in_array($currentPage, $protected_pages)) {
    if (!isset($_SESSION['user_id'])) {
        // ログインしていない場合、ログインページにリダイレクト
        header("Location: /login.php");
        exit(); // リダイレクト後、スクリプトの実行を停止
    }
    // ログイン済みの場合、そのまま続行
}

// ページのタイトルを設定
$pageTitle = "Tiper Live";
if ($currentPage === 'users_admin') {
    $pageTitle = "Tiper Live - ユーザー管理";
} elseif ($currentPage === 'products_admin') {
    $pageTitle = "Tiper Live - 商品登録";
} elseif ($currentPage === 'duga_products_page') {
    $pageTitle = "Duga 商品一覧 - Tiper Live"; // Dugaページ用のタイトル
} elseif ($currentPage === 'duga_product_detail') { // ★追加: 個別ページ用のタイトル
    $pageTitle = "Duga 商品詳細 - Tiper Live";
}
// 必要に応じて、他のページのタイトルもここで設定可能

?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <!-- 既存のインクルードファイルを維持 -->
    <?php include_once __DIR__ . '/../includes/head.php'; ?>
</head>

<body>
    <?php include_once __DIR__ . '/../includes/header.php'; ?>
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="p-3 grid-main">
        <main id="main-content-area">
            <?php
            // $currentPage に基づいてメインコンテンツを読み込む
            switch ($currentPage) {
                case 'users_admin':
                    // ユーザー管理ページの場合
                    include_once __DIR__ . '/users_admin_crud.php';
                    break;
                case 'products_admin':
                    // 商品登録ページの場合
                    include_once __DIR__ . '/products_admin.php';
                    break;
                case 'duga_products_page': // Duga専用ページの場合
                    include_once __DIR__ . '/duga_products.php';
                    break;
                case 'duga_product_detail': // ★追加: Duga商品個別ページの場合
                    include_once __DIR__ . '/duga_product_detail.php';
                    break;
                case 'home':
                default:
                    // 通常のホームページコンテンツ
                    ?>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb bg-light p-3 rounded shadow-sm">
                            <li class="breadcrumb-item"><a href="/"><i class="fas fa-home me-1"></i>ホーム</a></li>
                            <li class="breadcrumb-item"><a href="#"><i class="fas fa-list me-1"></i>カテゴリ</a></li>
                            <li class="breadcrumb-item active" aria-current="page"><i class="fas fa-file me-1"></i>現在のページ</li>
                        </ol>
                    </nav>
                    <div class="container-fluid bg-white p-4 rounded shadow-sm mt-3">
                        <h1 class="mb-4"><i class="fas fa-clipboard-list me-2"></i>メインコンテンツタイトル</h1>
                        <p>ここにあなたのサイトの主要なコンテンツが入ります。テキスト、画像、フォームなどを配置しましょう。</p>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title"><i class="fas fa-info-circle me-2"></i>コンテンツブロック 1</h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title"><i class="fas fa-lightbulb me-2"></i>コンテンツブロック 2</h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title"><i class="fas fa-images me-2"></i>ギャラリー</h5>
                                        <div class="row">
                                            <div class="col-md-4 mb-2"><img src="/img/photos/download.png" class="img-fluid rounded" alt="Placeholder Image"></div>
                                            <div class="col-md-4 mb-2"><img src="/img/photos/download.png" class="img-fluid rounded" alt="Placeholder Image"></div>
                                            <div class="col-md-4 mb-2"><img src="/img/photos/download.png" class="img-fluid rounded" alt="Placeholder Image"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
                    break;
            }
            ?>
        </main><!-- #main-content-area -->
        <?php include_once __DIR__ . '/../includes/footer.php'; ?>
    </div>
    <?php include_once __DIR__ . '/../includes/scripts.php'; ?>
</body>

</html>
