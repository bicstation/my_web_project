<?php
// C:\doc\my_web_project\app\public\login.php
// ログインページ

// 共通初期化ファイルを読み込む（セッションハンドラ設定とsession_start()を含む）
require_once __DIR__ . '/init.php';

// Debugging: ログインページのアクセス時にセッション情報をログに出力
error_log("Login page accessed. Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET'));

// ログイン済みの場合、ダッシュボードまたはホームにリダイレクト
if (isset($_SESSION['user_id'])) {
    error_log("Already logged in, redirecting to home. User ID: " . $_SESSION['user_id']);
    header("Location: /"); // ログイン後はトップページにリダイレクト（後で管理ダッシュボードに変更）
    exit();
}

$login_message = "";

// -----------------------------------------------------
// ログイン処理
// -----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // 環境変数からデータベース接続情報を取得
    $db_host = getenv('DB_HOST');
    $db_name = getenv('DB_NAME');
    $db_user = getenv('DB_USER');
    $db_password = getenv('DB_PASSWORD');

    $conn = new mysqli($db_host, $db_user, $db_password, $db_name);

    if ($conn->connect_error) {
        $login_message = "<div class='alert alert-danger'>データベース接続エラー: " . $conn->connect_error . "</div>";
        error_log("Login DB connection error: " . $conn->connect_error);
    } else {
        $stmt = $conn->prepare("SELECT id, username, email, password_hash FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password_hash'])) {
                // ログイン成功
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['username'];
                $_SESSION['user_email'] = $user['email'];
                $login_message = "<div class='alert alert-success'>ログイン成功！</div>";
                
                // Debugging: ログイン成功時にセッション情報がセットされたことをログに出力
                error_log("Login successful! User ID set in session: " . $_SESSION['user_id'] . ", Username: " . $_SESSION['user_name']);

                // ★追加: リダイレクト直前のセッション内容をログに出力
                error_log("Login.php - SESSION before redirect: " . print_r($_SESSION, true));

                // セッションにデータが書き込まれたので、すぐにリダイレクト
                header("Location: /");
                exit();
            } else {
                $login_message = "<div class='alert alert-danger'>Eメールまたはパスワードが正しくありません。</div>";
                error_log("Login failed: Password verification failed for email: " . $email);
            }
        } else {
            $login_message = "<div class='alert alert-danger'>Eメールまたはパスワードが正しくありません。</div>";
            error_log("Login failed: User not found or multiple users for email: " . $email);
        }
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .login-container {
            background-color: #fff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        .login-container h2 {
            margin-bottom: 30px;
            color: #007bff;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>ログイン</h2>
        <?php if (!empty($login_message)) { echo $login_message; } ?>
        <form method="POST" action="">
            <input type="hidden" name="action" value="login">
            <div class="mb-3">
                <label for="email" class="form-label">Eメール</label>
                <input type="email" class="form-control" id="email" name="email" required autocomplete="username">
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">パスワード</label>
                <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
            </div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">ログイン</button>
            </div>
        </form>
    </div>
</body>
</html>
