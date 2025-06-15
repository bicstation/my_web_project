<?php
// C:\project\my_web_project\app\public\index.php

// Composerのオートローダーを読み込む
// これにより、App名前空間下のクラスや、vlucas/phpdotenvなどのComposerが管理するライブラリが自動的にロードされます。
// !!! 重要: このファイルの先頭に空白やBOMがないことを確認してください !!!
require_once __DIR__ . '/../../vendor/autoload.php';

// Dotenvライブラリを使って.envファイルをロード (オートローダーの後に配置)
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// 共通初期化ファイルを読み込む（セッションハンドラ設定とsession_start()を含む）
// init.php がセッションを開始する前に、ここまでの出力がないことを確認することが重要。
require_once __DIR__ . '/../init.php'; // パスを app/init.php に修正

// 名前空間を使用するクラスをインポート
use App\Core\Logger;
use App\Core\Database;
use App\Core\Session; // Sessionクラスをインポート

// データベース接続設定を.envから取得
$dbConfig = [
    'host'      => $_ENV['DB_HOST'] ?? 'localhost',
    'dbname'    => $_ENV['DB_NAME'] ?? 'web_project_db',
    'user'      => $_ENV['DB_USER'] ?? 'root',
    'pass'      => $_ENV['DB_PASS'] ?? 'password',
    'charset'   => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
];

// ロガーとデータベース接続をグローバルで利用可能にする（sidebar.phpなどからアクセスするため）
global $logger, $database, $pdo;
try {
    $logger = new Logger('main_app.log'); // Loggerクラスがロードされる
    $logger->info("メインアプリケーション (index.php) へのアクセス処理を開始します。");
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

// Debugging line: 現在のページとセッションのユーザーIDをPHPエラーログに出力
error_log("Current page requested: " . $currentPage . ", User ID in session: " . (Session::getUserId() ?? 'NOT SET'));


// ====================================================================
// !!! 重要: すべてのPOSTリクエスト処理とリダイレクトは、ここで行うべきです。
// HTML出力が始まる前に実行されることが重要です。
// ====================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($currentPage) {
        case 'login':
            // ログイン処理をここに直接記述、または専用のプロセッサファイルをインクルード
            $submittedCsrfToken = $_POST['csrf_token'] ?? '';
            if (!Session::has('csrf_token') || $submittedCsrfToken !== Session::get('csrf_token')) {
                Session::set('flash_message', "<div class='alert alert-danger'>不正なリクエストです。ページを再読み込みしてください。</div>");
                $logger->error("CSRFトークン検証失敗: " . ($_SERVER['REMOTE_ADDR'] ?? '不明なIP'));
                // 不正なリクエストの場合は、ここで処理を続行せず、ログインページに戻る
                header('Location: index.php?page=login');
                exit();
            } else {
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';

                if (empty($username) || empty($password)) {
                    Session::set('flash_message', "<div class='alert alert-danger'>ユーザー名とパスワードを入力してください。</div>");
                } else {
                    try {
                        // username または email でユーザーを検索
                        $stmt = $pdo->prepare("SELECT id, username, password_hash, role FROM users WHERE username = :username_param OR email = :email_param LIMIT 1");
                        $stmt->execute([
                            ':username_param' => $username,
                            ':email_param'    => $username
                        ]);
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($user && password_verify($password, $user['password_hash'])) {
                            Session::login($user['id'], $user['username'], $user['role']);
                            Session::set('flash_message', "<div class='alert alert-success'>ログインに成功しました！ようこそ、" . htmlspecialchars($user['username']) . "さん！</div>");
                            header('Location: index.php?page=dashboard');
                            exit();
                        } else {
                            Session::set('flash_message', "<div class='alert alert-danger'>ユーザー名またはパスワードが間違っています。</div>");
                            $logger->warning("ログイン失敗: 無効な認証情報 (ユーザー名/メール: {$username})。");
                        }
                    } catch (PDOException $e) {
                        Session::set('flash_message', "<div class='alert alert-danger'>データベースエラーが発生しました: " . htmlspecialchars($e->getMessage()) . "</div>");
                        error_log("Login DB error: " . $e->getMessage());
                        $logger->error("ログインデータベースエラー: " . $e->getMessage());
                    } catch (Exception $e) {
                        Session::set('flash_message', "<div class='alert alert-danger'>アプリケーションエラーが発生しました: " . htmlspecialchars($e->getMessage()) . "</div>");
                        error_log("Login application error: " . $e->getMessage());
                        $logger->error("ログインアプリケーションエラー: " . $e->getMessage());
                    }
                }
            }
            // ログイン失敗の場合はHTML出力に進む
            break;

        case 'logout':
            Session::logout(); // ログアウト処理
            Session::set('flash_message', "<div class='alert alert-success'>ログアウトしました。</div>");
            header('Location: index.php?page=home'); // ログアウト後にホームにリダイレクト
            exit();
            break;

        // 他のページのPOST処理もここに追加していく (例: register, product_adminなど)
        case 'register':
            // register.php の POST 処理をここに移動するか、
            // 別ファイルに分離して require_once する
            break;

        // ... その他の POST 処理 ...
    }
}

// ====================================================================
// アクセス権限チェックとリダイレクトもHTML出力前に行う
// ====================================================================

// 管理画面へのアクセスチェックとリダイレクト
$protected_pages = ['users_admin', 'products_admin', 'dashboard', 'profile']; // 保護したい管理ページを追加

if (in_array($currentPage, $protected_pages)) {
    if (!Session::isLoggedIn()) {
        Session::set('flash_message', "<div class='alert alert-warning'>このページにアクセスするにはログインが必要です。</div>");
        header("Location: index.php?page=login");
        exit();
    }

    if (($currentPage === 'users_admin' || $currentPage === 'products_admin') && Session::getUserRole() !== 'admin') {
        Session::set('flash_message', "<div class='alert alert-danger'>このページにアクセスする権限がありません。</div>");
        header('Location: index.php?page=home');
        exit();
    }
}


// ページのタイトルを設定 (これはHTML出力なので、この位置で問題ありません)
$pageTitle = "Tiper Live";
if ($currentPage === 'users_admin') {
    $pageTitle = "Tiper Live - ユーザー管理";
} elseif ($currentPage === 'products_admin') {
    $pageTitle = "Tiper Live - 商品登録";
} elseif ($currentPage === 'duga_products_page') {
    $pageTitle = "Duga 商品一覧 - Tiper Live";
} elseif ($currentPage === 'duga_product_detail') {
    $pageTitle = "Duga 商品詳細 - Tiper Live";
} elseif ($currentPage === 'dashboard') {
    $pageTitle = "Tiper Live - ダッシュボード";
} elseif ($currentPage === 'profile') {
    $pageTitle = "Tiper Live - プロフィール";
} elseif ($currentPage === 'login') {
    $pageTitle = "Tiper Live - ログイン";
} elseif ($currentPage === 'register') {
    $pageTitle = "Tiper Live - 新規登録";
}


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
            // フラッシュメッセージの表示 (一回だけ表示して削除)
            // POST処理がHTML出力前に完了しているため、ここで安全に表示できます
            if (Session::has('flash_message')) {
                echo Session::get('flash_message');
                Session::remove('flash_message');
            }
            ?>

            <?php
            // $currentPage に基づいてメインコンテンツを読み込む
            $contentPath = '';
            switch ($currentPage) {
                case 'login':
                    // login.php はフォームの表示のみを行う（POST処理はindex.phpで行う）
                    $contentPath = __DIR__ . '/login.php';
                    break;
                case 'register':
                    $contentPath = __DIR__ . '/register.php';
                    break;
                case 'users_admin':
                    $contentPath = __DIR__ . '/users_admin_crud.php';
                    break;
                case 'products_admin':
                    $contentPath = __DIR__ . '/products_admin.php';
                    break;
                case 'duga_products_page':
                    $contentPath = __DIR__ . '/duga_products.php';
                    break;
                case 'duga_product_detail':
                    $contentPath = __DIR__ . '/duga_product_detail.php';
                    break;
                case 'dashboard':
                    $contentPath = __DIR__ . '/dashboard.php';
                    break;
                case 'profile':
                    $contentPath = __DIR__ . '/profile.php';
                    break;
                case 'home':
                default:
                    // 通常のホームページコンテンツ
                    $contentPath = null; // デフォルトコンテンツを直接HTMLで記述するため
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

            // コンテンツファイルをインクルード
            if ($contentPath && file_exists($contentPath)) {
                include_once $contentPath;
            } elseif ($contentPath && !file_exists($contentPath)) {
                error_log("Requested content file not found: " . $contentPath);
                include_once __DIR__ . '/404.php';
            }
            ?>
        </main><!-- #main-content-area -->
        <?php include_once __DIR__ . '/../includes/footer.php'; ?>
    </div>
    <?php include_once __DIR__ . '/../includes/scripts.php'; ?>
</body>

</html>
