<?php
// C:\project\my_web_project\app\public\login.php

// エラーレポートの設定
ini_set('display_errors', 1);
error_reporting(E_ALL);

// init.php を読み込むことで、Composerのオートロード、セッション開始、環境変数のロードなどが行われます。
// public/login.php から見て project_root/app/init.php へのパス
require_once __DIR__ . '/../../app/init.php';

use App\Core\Database;
use App\Core\Logger;
use App\Core\Session;

// Loggerインスタンスをここで初期化し、スクリプト全体で利用可能にする
// これにより、CSRFトークン検証失敗時など、早期のログ記録が必要な場合にも対応できます。
$logger = new Logger('login.log');

// フラッシュメッセージを取得し、セッションから削除
$message = Session::get('flash_message');
Session::delete('flash_message');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRFトークンの検証
    $submittedCsrfToken = $_POST['csrf_token'] ?? '';
    if (!Session::has('csrf_token') || $submittedCsrfToken !== Session::get('csrf_token')) {
        $message = "<div class='alert alert-danger'>不正なリクエストです。ページを再読み込みしてください。</div>";
        // ここで、$logger インスタンスを使ってエラーをログに記録します。
        $logger->error("CSRFトークン検証失敗: " . ($_SERVER['REMOTE_ADDR'] ?? '不明なIP'));
        // 不正なリクエストの場合はここで処理を終了し、フォームを再表示するのが安全
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $message = "<div class='alert alert-danger'>ユーザー名とパスワードを入力してください。</div>";
        } else {
            try {
                // データベース設定は .env ファイルからロードされた $_ENV を使用
                // デフォルト値は開発環境用
                $dbConfig = [
                    'host'    => $_ENV['DB_HOST'] ?? 'localhost',
                    'dbname'  => $_ENV['DB_NAME'] ?? 'web_project_db',
                    'user'    => $_ENV['DB_USER'] ?? 'root',
                    'pass'    => $_ENV['DB_PASS'] ?? 'password',
                    'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
                ];

                // DatabaseインスタンスにLoggerインスタンスを渡す
                $database = new Database($dbConfig, $logger);
                $pdo = $database->getConnection();

                // 修正点: username または email でユーザーを検索するSQLクエリのプレースホルダーを明確に分ける
                // これにより、Invalid parameter number エラーを回避します。
                $stmt = $pdo->prepare("SELECT id, username, password_hash, role FROM users WHERE username = :username_param OR email = :email_param LIMIT 1");
                $stmt->execute([
                    ':username_param' => $username, // ユーザー名としてバインド
                    ':email_param'    => $username  // メールアドレスとしてバインド
                ]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password_hash'])) {
                    // 認証成功！セッションにユーザー情報を保存
                    Session::login($user['id'], $user['username'], $user['role']);
                    Session::set('flash_message', "<div class='alert alert-success'>ログインに成功しました！ようこそ、" . htmlspecialchars($user['username']) . "さん！</div>");

                    // ログイン成功後はダッシュボードまたはホームにリダイレクト
                    header('Location: index.php?page=dashboard');
                    exit(); // リダイレクト後は必ずexit()でスクリプトの実行を終了
                } else {
                    $message = "<div class='alert alert-danger'>ユーザー名またはパスワードが間違っています。</div>";
                    $logger->warning("ログイン失敗: 無効な認証情報 (ユーザー名/メール: {$username})。");
                }

            } catch (PDOException $e) {
                $message = "<div class='alert alert-danger'>データベースエラーが発生しました: " . htmlspecialchars($e->getMessage()) . "</div>";
                error_log("Login DB error: " . $e->getMessage());
                // Loggerクラスがあればそちらにも記録
                $logger->error("ログインデータベースエラー: " . $e->getMessage());
            } catch (Exception $e) {
                $message = "<div class='alert alert-danger'>アプリケーションエラーが発生しました: " . htmlspecialchars($e->getMessage()) . "</div>";
                error_log("Login application error: " . $e->getMessage());
                $logger->error("ログインアプリケーションエラー: " . $e->getMessage());
            }
        }
    }
}

// フォームに表示するCSRFトークンを取得
$csrfToken = Session::get('csrf_token');
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン</title>
    <!-- Bootstrap CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }
        .container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .card {
            border-radius: 15px; /* 丸みを帯びた角 */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .card-title {
            color: #343a40;
            font-weight: bold;
        }
        .form-label {
            font-weight: 500;
            color: #495057;
        }
        .form-control {
            border-radius: 8px;
            padding: 10px 15px;
            border: 1px solid #ced4da;
        }
        .form-control:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25);
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 1.1rem;
            font-weight: bold;
            transition: background-color 0.2s ease-in-out;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        .alert {
            border-radius: 8px;
        }
        a {
            color: #007bff;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card shadow-lg p-4" style="width: 100%; max-width: 400px;">
            <div class="card-body">
                <h2 class="card-title text-center mb-4">ログイン</h2>
                <?php if (!empty($message)): ?>
                    <?= $message ?>
                <?php endif; ?>
                <form action="index.php?page=login" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <div class="mb-3">
                        <label for="username" class="form-label">ユーザー名またはメールアドレス</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">パスワード</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mt-3">ログイン</button>
                </form>
                <div class="text-center mt-3">
                    <p>アカウントをお持ちではありませんか？ <a href="index.php?page=register">新規登録</a></p>
                </div>
            </div>
        </div>
    </div>
    <!-- Bootstrap JS Bundle CDN (Popper included) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
