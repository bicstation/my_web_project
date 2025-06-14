<?php
// my_web_project/app/public/dashboard.php

require __DIR__ . '/includes/config.php';

// ログインしていなければログインページにリダイレクト
redirect_if_not_logged_in();

// セッション情報からユーザー名を取得
$username = $_SESSION['username'];
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ダッシュボード</title>
    <style>
        body { font: 14px sans-serif; text-align: center; }
        .wrapper { width: 600px; padding: 20px; margin: 50px auto; border: 1px solid #ddd; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .welcome-message { font-size: 1.5em; margin-bottom: 20px; }
        .info-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .info-table th, .info-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .info-table th { background-color: #f2f2f2; }
        .logout-link { margin-top: 30px; display: inline-block; padding: 10px 20px; background-color: #dc3545; color: white; border-radius: 3px; text-decoration: none; }
        .logout-link:hover { background-color: #c82333; }
    </style>
</head>
<body>
    <div class="wrapper">
        <h2 class="welcome-message">ようこそ、<?php echo htmlspecialchars($username); ?> さん！</h2>
        <p>これはログイン後にのみアクセスできるダッシュボードページです。</p>

        <h3>セッション情報</h3>
        <table class="info-table">
            <thead>
                <tr>
                    <th>キー</th>
                    <th>値</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($_SESSION as $key => $value): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($key); ?></td>
                        <td><?php echo htmlspecialchars(print_r($value, true)); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p><a href="logout.php" class="logout-link">ログアウト</a></p>
    </div>
</body>
</html>