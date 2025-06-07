<?php
// my_web_project/app/public/admin/index.php
require __DIR__ . '/../includes/config.php'; // 親ディレクトリのincludes/config.php を読み込む

// ログインが必要な管理画面であれば、ここでチェック
// redirect_if_not_logged_in(); // 必要であれば

echo "<h1>Welcome to Admin Panel!</h1>";
echo "<p>This is the admin.tipers.live dashboard.</p>";
echo "<p>User: " . (isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'ゲスト') . "</p>";
echo "<p><a href=\"/\">メインサイトに戻る</a></p>"; // 管理画面のルートへのリンク

// 必要に応じて、ログイン/ログアウトリンクなども追加
// echo "<p><a href=\"/admin/login.php\">管理ログイン</a></p>";
// echo "<p><a href=\"/admin/logout.php\">管理ログアウト</a></p>";
?>