<?php
// データベース接続など、ページ共通の前処理があればここに

// 必要に応じて、ページのタイトルなどを設定
$pageTitle = "My Awesome Site";
$currentPage = "home"; // 現在のページを識別する変数
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <?php include_once __DIR__ . '/../includes/head.php'; ?>
</head>
<body>
    <?php include_once __DIR__ . '/../includes/header.php'; ?>

    <div class="d-flex flex-column flex-md-row" id="main-content-wrapper">
        <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
        <div class="content-wrapper flex-grow-1">
            <?php #include_once __DIR__ . '/../includes/navbar.php'; ?>
            <?php include_once __DIR__ . '/main_content.php'; ?>
        </div>
    </div>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/js/script.js"></script>
</body>
</html>