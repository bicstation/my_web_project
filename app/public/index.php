<?php
// C:\project\my_web_project\app\public\index.php

// エラーレポート設定 (開発中はこれらを有効にするのがベスト)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Composerのオートローダーを読み込む - これは常にファイルの早い段階で必要です
require_once __DIR__ . '/../../vendor/autoload.php';

// 名前空間を使用するクラスをインポート
// Composerのオートロード設定により、これらのクラスが自動的に読み込まれます
use App\Core\Logger;
use App\Core\Database;
use App\Core\Session; // App\Core\Session クラスをインポート

// === init.php から移動したセッション初期化ロジック ===
// !!! ここが重要: 全てのecho文やHTML出力より前にセッションを開始する !!!
if (session_status() == PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 3600, // 1時間
        'path' => '/',
        'domain' => '', // 必要に応じてドメインを指定 (例: '.yourdomain.com')
        'secure' => true, // HTTPSでのみクッキーを送信
        'httponly' => true, // JavaScriptからのアクセスを禁止
        'samesite' => 'Lax' // CSRF対策
    ]);
    session_name('MYAPPSESSID'); // セッションクッキー名を指定
    session_start();
}
// === セッション初期化ロジックここまで ===

// デバッグ用: .env ファイルが正しく読み込まれているか確認
// !!! デバッグ出力はセッション開始後に移動するか、開発環境でのみ使用し、本番では削除/コメントアウトする !!!
// echo "<pre>DB_HOST: " . ($_ENV['DB_HOST'] ?? 'NOT SET') . "</pre>";
// echo "<pre>DB_NAME: " . ($_ENV['DB_NAME'] ?? 'NOT SET') . "</pre>";
// echo "<pre>DB_USER: " . ($_ENV['DB_USER'] ?? 'NOT SET') . "</pre>";
// echo "<pre>DB_PASS: " . ($_ENV['DB_PASS'] ?? 'NOT SET') . "</pre>";
// echo "<pre>_ENV array: ";
// print_r($_ENV);
// echo "</pre>";
// die("Environment variable check complete."); // これで処理を停止し、出力だけを確認

// 

// 
// 
// 

// データベース接続設定を.envから取得
$dbConfig = [
    'host'      => $_ENV['DB_HOST'] ?? 'localhost',
    'dbname'    => $_ENV['DB_NAME'] ?? 'web_project_db',
    'user'      => $_ENV['DB_USER'] ?? 'root',
    'pass'      => $_ENV['DB_PASS'] ?? 'password',
    'charset'   => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
];


// ロガーとデータベース接続をグローバルで利用可能にする
global $logger, $database, $pdo;
try {
    $logger = new Logger('main_app.log'); // Loggerクラスのインスタンス化
    $logger->info("メインアプリケーション (index.php) へのアクセス処理を開始します。");
    $database = new Database($dbConfig, $logger); // Databaseクラスのインスタンス化
    $pdo = $database->getConnection();
} catch (Exception $e) {
    error_log("Main application initialization error: " . $e->getMessage());
    die("サイトの初期化中にエラーが発生しました。ログを確認してください。");
}

// ====================================================================
// !!! 重要: CSRFトークンをここで生成/確認します。
// これにより、後続のPOSTリクエスト検証時に必ずトークンが存在します。
// ====================================================================
if (!Session::has('csrf_token')) {
    Session::generateCsrfToken();
}
// ユーザーの活動時間を更新し、一定期間操作がない場合は自動的にログアウトさせる
if (Session::isLoggedIn()) {
    $lastActivity = Session::get('last_activity');
    $inactiveTime = 1800; // 30分

    if (time() - $lastActivity > $inactiveTime) {
        Session::logout();
        Session::set('flash_message', "<div class='alert alert-warning'>セッションがタイムアウトしました。再度ログインしてください。</div>");
        header('Location: index.php?page=login');
        exit();
    }
    Session::set('last_activity', time()); // アクティビティを更新
}


// URLのクエリパラメータ 'page' を取得、またはホスト名に基づいてページを決定
$currentPage = $_GET['page'] ?? 'home';
$isDugaDomain = ($_SERVER['HTTP_HOST'] === 'duga.tipers.live');

if ($isDugaDomain) {
    if (isset($_GET['page'])) {
        if ($_GET['page'] === 'duga_product_detail') {
            $currentPage = 'duga_product_detail';
        } else {
            $currentPage = 'duga_products_page';
        }
    } else {
        $currentPage = 'duga_products_page';
    }
}

error_log("Current page requested: " . $currentPage . ", User ID in session: " . (Session::getUserId() ?? 'NOT SET'));


// ====================================================================
// !!! 重要: すべてのPOSTリクエスト処理とリダイレクトは、ここで行うべきです。
// HTML出力が始まる前に実行されることが重要です。
// ====================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($currentPage) {
        case 'login':
            $submittedCsrfToken = $_POST['csrf_token'] ?? '';
            // SessionクラスのverifyCsrfTokenメソッドを使用
            if (!Session::verifyCsrfToken($submittedCsrfToken)) {
                Session::set('flash_message', "<div class='alert alert-danger'>不正なリクエストです。ページを再読み込みしてください。</div>");
                $logger->error("CSRFトークン検証失敗: " . ($_SERVER['REMOTE_ADDR'] ?? '不明なIP'));
                header('Location: index.php?page=login');
                exit();
            } else {
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';

                if (empty($username) || empty($password)) {
                    Session::set('flash_message', "<div class='alert alert-danger'>ユーザー名とパスワードを入力してください。</div>");
                } else {
                    try {
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
            break;

        case 'logout':
            // ログアウトフォームからのPOSTリクエストにCSRFトークンチェックを追加することが推奨される
            if (!Session::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
                Session::set('flash_message', "<div class='alert alert-danger'>不正なリクエストです。ログアウトできませんでした。</div>");
                $logger->error("CSRFトークン検証失敗: Logout");
                header('Location: index.php?page=dashboard'); // ログアウト失敗時はダッシュボードに戻すなど
                exit();
            }
            Session::logout();
            Session::set('flash_message', "<div class='alert alert-success'>ログアウトしました。</div>");
            header('Location: index.php?page=home');
            exit();
            break;

        case 'register':
            if (!Session::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
                Session::set('flash_message', "<div class='alert alert-danger'>不正なリクエストです。ページを再読み込みしてください。</div>");
                $logger->error("CSRFトークン検証失敗: Register");
                header('Location: index.php?page=register');
                exit();
            }
            $reg_username = trim($_POST['username'] ?? '');
            $reg_email = trim($_POST['email'] ?? '');
            $reg_password = $_POST['password'] ?? '';
            $reg_password_confirm = $_POST['password_confirm'] ?? '';

            if (empty($reg_username) || empty($reg_email) || empty($reg_password) || empty($reg_password_confirm)) {
                Session::set('flash_message', "<div class='alert alert-danger'>すべてのフィールドを入力してください。</div>");
            } elseif ($reg_password !== $reg_password_confirm) {
                Session::set('flash_message', "<div class='alert alert-danger'>パスワードが一致しません。</div>");
            } else {
                try {
                    // ユーザー名またはメールが既に存在するかチェック
                    $stmt_check = $pdo->prepare("SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1");
                    $stmt_check->execute([':username' => $reg_username, ':email' => $reg_email]);
                    if ($stmt_check->fetch()) {
                        Session::set('flash_message', "<div class='alert alert-danger'>そのユーザー名またはメールアドレスは既に登録されています。</div>");
                    } else {
                        $password_hash = password_hash($reg_password, PASSWORD_DEFAULT);
                        $stmt_insert = $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (:username, :email, :password_hash, 'user')");
                        if ($stmt_insert->execute([':username' => $reg_username, ':email' => $reg_email, ':password_hash' => $password_hash])) {
                            Session::set('flash_message', "<div class='alert alert-success'>登録が完了しました！ログインしてください。</div>");
                            header('Location: index.php?page=login');
                            exit();
                        } else {
                            Session::set('flash_message', "<div class='alert alert-danger'>登録に失敗しました。</div>");
                            $logger->error("ユーザー登録データベースエラー: " . json_encode($stmt_insert->errorInfo()));
                        }
                    }
                } catch (PDOException $e) {
                    Session::set('flash_message', "<div class='alert alert-danger'>データベースエラーが発生しました: " . htmlspecialchars($e->getMessage()) . "</div>");
                    error_log("Register DB error: " . $e->getMessage());
                    $logger->error("ユーザー登録データベースエラー: " . $e->getMessage());
                }
            }
            break;

        case 'users_admin': // === ここからusers_admin_crud.phpからの移動 ===
            // 管理者権限チェック（POST処理前）
            if (!Session::isLoggedIn() || Session::getUserRole() !== 'admin') {
                Session::set('flash_message', '<div class="alert alert-danger">このページへのアクセス権限がありません。</div>');
                header('Location: index.php?page=home');
                exit();
            }

            $action = $_POST['action'] ?? '';
            $submittedCsrfToken = $_POST['csrf_token'] ?? '';

            // CSRFトークン検証
            if (!Session::verifyCsrfToken($submittedCsrfToken)) {
                Session::set('flash_message', '<div class="alert alert-danger">不正なリクエストです。ページを再読み込みしてください。</div>');
                $logger->error("CSRFトークン検証失敗: users_admin_crud - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'));
                header('Location: index.php?page=users_admin');
                exit();
            }

            try {
                switch ($action) {
                    case 'add':
                        $username = trim($_POST['username'] ?? '');
                        $email = trim($_POST['email'] ?? '');
                        $password = $_POST['password'] ?? '';
                        $role = $_POST['role'] ?? 'user';

                        if (empty($username) || empty($email) || empty($password)) {
                            Session::set('flash_message', '<div class="alert alert-danger">ユーザー名、メール、パスワードは必須です。</div>');
                        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            Session::set('flash_message', '<div class="alert alert-danger">無効なメールアドレス形式です。</div>');
                        } else {
                            // ユーザー名またはメールが既に存在するかチェック
                            $stmt_check = $pdo->prepare("SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1");
                            $stmt_check->execute([':username' => $username, ':email' => $email]);
                            if ($stmt_check->fetch()) {
                                Session::set('flash_message', '<div class="alert alert-danger">そのユーザー名またはメールアドレスは既に登録されています。</div>');
                            } else {
                                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (:username, :email, :password_hash, :role)");
                                if ($stmt->execute([':username' => $username, ':email' => $email, ':password_hash' => $passwordHash, ':role' => $role])) {
                                    Session::set('flash_message', '<div class="alert alert-success">ユーザーが追加されました。</div>');
                                    $logger->info("ユーザー追加成功: ID - " . $pdo->lastInsertId() . ", Username - " . $username . ", Role - " . $role);
                                } else {
                                    Session::set('flash_message', '<div class="alert alert-danger">ユーザーの追加に失敗しました。</div>');
                                    $logger->error("ユーザー追加失敗: " . json_encode($stmt->errorInfo()));
                                }
                            }
                        }
                        break;

                    case 'edit':
                        $id = $_POST['id'] ?? 0;
                        $username = trim($_POST['username'] ?? '');
                        $email = trim($_POST['email'] ?? '');
                        $role = $_POST['role'] ?? 'user';
                        $password = $_POST['password'] ?? ''; // パスワードはオプション

                        if (empty($id) || empty($username) || empty($email)) {
                            Session::set('flash_message', '<div class="alert alert-danger">ユーザーID、ユーザー名、メールは必須です。</div>');
                        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            Session::set('flash_message', '<div class="alert alert-danger">無効なメールアドレス形式です。</div>');
                        } else {
                            // 他のユーザーが同じユーザー名またはメールアドレスを使用していないかチェック (自分自身は除く)
                            $stmt_check = $pdo->prepare("SELECT id FROM users WHERE (username = :username OR email = :email) AND id != :id LIMIT 1");
                            $stmt_check->execute([':username' => $username, ':email' => $email, ':id' => $id]);
                            if ($stmt_check->fetch()) {
                                Session::set('flash_message', '<div class="alert alert-danger">そのユーザー名またはメールアドレスは既に他のユーザーに使用されています。</div>');
                            } else {
                                $sql = "UPDATE users SET username = :username, email = :email, role = :role";
                                $params = [
                                    ':username' => $username,
                                    ':email' => $email,
                                    ':role' => $role,
                                    ':id' => $id
                                ];

                                if (!empty($password)) {
                                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                                    $sql .= ", password_hash = :password_hash";
                                    $params[':password_hash'] = $passwordHash;
                                }
                                $sql .= " WHERE id = :id";
                                $stmt = $pdo->prepare($sql);

                                if ($stmt->execute($params)) {
                                    Session::set('flash_message', '<div class="alert alert-success">ユーザーが更新されました。</div>');
                                    $logger->info("ユーザー更新成功: ID - " . $id . ", Username - " . $username . ", Role - " . $role);
                                } else {
                                    Session::set('flash_message', '<div class="alert alert-danger">ユーザーの更新に失敗しました。</div>');
                                    $logger->error("ユーザー更新失敗: " . json_encode($stmt->errorInfo()));
                                }
                            }
                        }
                        break;

                    case 'delete':
                        $id = $_POST['id'] ?? 0;
                        if (!empty($id)) {
                            // 削除対象のユーザーが自分自身ではないことを確認
                            if ($id == Session::getUserId()) {
                                Session::set('flash_message', '<div class="alert alert-danger">自分自身のアカウントは削除できません。</div>');
                                $logger->warning("自分自身のアカウント削除試行: User ID - " . Session::getUserId());
                            } else {
                                $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
                                if ($stmt->execute([':id' => $id])) {
                                    Session::set('flash_message', '<div class="alert alert-success">ユーザーが削除されました。</div>');
                                    $logger->info("ユーザー削除成功: ID - " . $id);
                                } else {
                                    Session::set('flash_message', '<div class="alert alert-danger">ユーザーの削除に失敗しました。</div>');
                                    $logger->error("ユーザー削除失敗: " . json_encode($stmt->errorInfo()));
                                }
                            }
                        }
                        break;
                }
            } catch (PDOException $e) {
                Session::set('flash_message', '<div class="alert alert-danger">データベースエラーが発生しました: ' . htmlspecialchars($e->getMessage()) . '</div>');
                $logger->error("ユーザー管理操作データベースエラー: " . $e->getMessage());
            } catch (Exception $e) {
                Session::set('flash_message', '<div class="alert alert-danger">予期せぬエラーが発生しました: ' . htmlspecialchars($e->getMessage()) . '</div>');
                $logger->error("ユーザー管理操作予期せぬエラー: " . $e->getMessage());
            }
            // users_adminページでのPOST処理後、常に同じページにリダイレクト
            header('Location: index.php?page=users_admin');
            exit();
            // === users_admin_crud.phpからの移動ここまで ===

        // 他のページに対するPOST処理もここに追加...
    }
}

// ====================================================================
// アクセス権限チェックとリダイレクトもHTML出力前に行う
// ====================================================================

$protected_pages = ['users_admin', 'products_admin', 'dashboard', 'profile'];

if (in_array($currentPage, $protected_pages)) {
    // Session::isLoggedIn() が Session クラスを介して呼び出される
    if (!Session::isLoggedIn()) {
        Session::set('flash_message', "<div class='alert alert-warning'>このページにアクセスするにはログインが必要です。</div>");
        header("Location: index.php?page=login");
        exit();
    }

    // Session::getUserRole() が Session クラスを介して呼び出される
    if (($currentPage === 'users_admin' || $currentPage === 'products_admin') && Session::getUserRole() !== 'admin') {
        Session::set('flash_message', "<div class='alert alert-danger'>このページにアクセスする権限がありません。</div>");
        header('Location: index.php?page=home');
        exit();
    }
}


// ページのタイトルを設定
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
    <?php include_once __DIR__ . '/../includes/head.php'; ?>
</head>

<body>
    <?php include_once __DIR__ . '/../includes/header.php'; ?>
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="p-3 grid-main">
        <main id="main-content-area">
            <?php
            // フラッシュメッセージの表示
            if (Session::has('flash_message')) {
                echo Session::get('flash_message');
                Session::remove('flash_message');
            }
            ?>

            <?php
            $contentPath = '';
            switch ($currentPage) {
                case 'login':
                    $contentPath = __DIR__ . '/login.php';
                    break;
                case 'register':
                    $contentPath = __DIR__ . '/register.php';
                    break;
                case 'users_admin':
                    $contentPath = __DIR__ . '/users_admin_crud.php'; // このファイルはPOST処理を含まない純粋な表示になる
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
                    $contentPath = null;
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

            if ($contentPath && file_exists($contentPath)) {
                include_once $contentPath;
            } elseif ($contentPath && !file_exists($contentPath)) {
                error_log("Requested content file not found: " . $contentPath);
                include_once __DIR__ . '/404.php';
            }
            ?>
        </main><?php include_once __DIR__ . '/../includes/footer.php'; ?>
    </div>
    <?php include_once __DIR__ . '/../includes/scripts.php'; ?>
</body>

</html>