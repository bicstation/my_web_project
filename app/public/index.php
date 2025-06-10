<?php
// データベース接続など、ページ共通の前処理があればここに
// 必要に応じて、ページのタイトルなどを設定
$pageTitle = "Tiper Live";
$currentPage = "home"; // 現在のページを識別する変数
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <?php include_once __DIR__ . '/../includes/head.php'; ?>
</head>

<body>
    <?php include_once __DIR__ . '/../includes/header.php'; ?>

    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="p-3 grid-main">
        <?php include_once __DIR__ . '/main_content.php'; ?>
        <?php include_once __DIR__ . '/../includes/footer.php'; ?>
    </div>
    <?php include_once __DIR__ . '/../includes/scripts.php'; ?>
</body>

</html>