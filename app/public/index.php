<?php
// C:\doc\my_web_project\app\public\index.php

// 共通初期化ファイルを読み込む（セッションハンドラ設定とsession_start()を含む）
require_once __DIR__ . '/init.php';

// URLのクエリパラメータ 'page' を取得
$currentPage = isset($_GET['page']) ? $_GET['page'] : 'home';

// Debugging line: 現在のページとセッションのユーザーIDをPHPエラーログに出力
error_log("Current page requested: " . $currentPage . ", User ID in session: " . ($_SESSION['user_id'] ?? 'NOT SET'));


// 管理画面へのアクセスチェックとリダイレクト
// ユーザー管理ページ (users_admin) と商品登録ページ (products_admin) はログインが必要なページとして保護
$protected_pages = ['users_admin', 'products_admin']; // ここに保護したい管理ページを追加

if (in_array($currentPage, $protected_pages)) {
    if (!isset($_SESSION['user_id'])) {
        // ログインしていない場合、ログインページにリダイレクト
        header("Location: /login.php");
        exit(); // リダイレクト後、スクリプトの実行を停止
    }
    // ログイン済みの場合、そのまま続行
}

// ページのタイトルを設定
$pageTitle = "Tiper Live";
if ($currentPage === 'users_admin') {
    $pageTitle = "Tiper Live - ユーザー管理";
} elseif ($currentPage === 'products_admin') {
    $pageTitle = "Tiper Live - 商品登録"; // 新しく追加
}
// 必要に応じて、他のページのタイトルもここで設定可能

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
            // $currentPage に基づいてメインコンテンツを読み込む
            switch ($currentPage) {
                case 'users_admin':
                    // ユーザー管理ページの場合
                    include_once __DIR__ . '/users_admin_crud.php';
                    break;
                case 'products_admin': // 新しく追加
                    // 商品登録ページの場合
                    include_once __DIR__ . '/products_admin.php';
                    break;
                case 'home':
                default:
                    // 通常のホームページコンテンツ
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
            ?>
        </main><!-- #main-content-area -->
        <?php include_once __DIR__ . '/../includes/footer.php'; ?>
    </div>
    <?php include_once __DIR__ . '/../includes/scripts.php'; ?>
</body>

</html>
