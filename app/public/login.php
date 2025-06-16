<?php
// C:\project\my_web_project\app\public\login.php

// Composerのオートローダーを読み込む (直接アクセスされた場合のために追加)
require_once __DIR__ . '/../../vendor/autoload.php';

// エラーレポートの設定 (init.phpで設定されている可能性が高いが、念のため)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// init.php は index.php で既に読み込まれているため、ここでは不要です。
// もし単独でアクセスされる可能性があるなら必要ですが、ルーティング経由なら不要です。
// require_once __DIR__ . '/../../app/init.php'; // 通常は不要

use App\Core\Session;

// フォームに表示するCSRFトークンを取得
// index.php の処理によってセッションに設定されていることを前提とします
$csrfToken = Session::get('csrf_token');
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
                <?php
                // フラッシュメッセージは index.php で表示されるため、ここでは不要です。
                // ただし、もし login.php が単独で直接アクセスされる可能性があるなら、
                // ここで改めて取得して表示してもよいでしょう。
                // if (!empty($message)): 
                //     <?= $message 
                //  //endif; 
                ?>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
