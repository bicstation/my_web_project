<?php
// C:\project\my_web_project\app\public\login.php

// init.php でセッションは既に開始されていることを前提とします
use App\Core\Database;
use App\Core\Logger;
use App\Core\Session; // Sessionクラスをuse宣言

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $message = "<div class='alert alert-danger'>ユーザー名とパスワードを入力してください。</div>";
    } else {
        try {
            // データベース設定は init.php でロードされているものを使用
            $dbConfig = [
                'host'    => $_ENV['DB_HOST'] ?? 'localhost',
                'dbname'  => $_ENV['DB_NAME'] ?? 'web_project_db',
                'user'    => $_ENV['DB_USER'] ?? 'root',
                'pass'    => $_ENV['DB_PASS'] ?? 'password',
                'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
            ];

            $logger = new Logger('login.log');
            $database = new Database($dbConfig, $logger);
            $pdo = $database->getConnection();

            $stmt = $pdo->prepare("SELECT id, username, password_hash, role FROM users WHERE username = :username OR email = :username LIMIT 1");
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                // 認証成功！セッションにユーザー情報を保存
                Session::login($user['id'], $user['username'], $user['role']);
                Session::set('flash_message', "<div class='alert alert-success'>ログインに成功しました！ようこそ、" . htmlspecialchars($user['username']) . "さん！</div>");

                // ログイン成功後はダッシュボードまたはホームにリダイレクト
                header('Location: index.php?page=dashboard');
                exit();
            } else {
                $message = "<div class='alert alert-danger'>ユーザー名またはパスワードが間違っています。</div>";
                $logger->warning("ログイン失敗: 無効な認証情報 (ユーザー名/メール: {$username})。");
            }

        } catch (PDOException $e) {
            $message = "<div class='alert alert-danger'>データベースエラーが発生しました: " . htmlspecialchars($e->getMessage()) . "</div>";
            error_log("Login DB error: " . $e->getMessage());
        } catch (Exception $e) {
            $message = "<div class='alert alert-danger'>アプリケーションエラーが発生しました: " . htmlspecialchars($e->getMessage()) . "</div>";
            error_log("Login application error: " . $e->getMessage());
        }
    }
}

// ログインフォームのHTML
?>
<div class="container d-flex justify-content-center align-items-center min-vh-100">
    <div class="card shadow-lg p-4" style="width: 100%; max-width: 400px;">
        <div class="card-body">
            <h2 class="card-title text-center mb-4">ログイン</h2>
            <?php if (!empty($message)): ?>
                <?= $message ?>
            <?php endif; ?>
            <form action="index.php?page=login" method="POST">
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