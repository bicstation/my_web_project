<?php
// C:\doc\my_web_project\app\includes\header.php

// データベース接続設定ファイルを読み込む
// 必要に応じて、db_config.phpが適切にPDOを返すか、mysqli接続を返すか確認してください
require_once __DIR__ . '/db_config.php';

$user_role = null;
$user_name = null;

// ログインしている場合、ユーザー情報を取得
if (isset($_SESSION['user_id'])) {
    error_log("Header: User ID in session: " . $_SESSION['user_id']);
    try {
        $pdo = connectDB(); // db_config.phpで定義されているデータベース接続関数
        $stmt = $pdo->prepare("SELECT username, is_admin FROM users WHERE id = :id");
        $stmt->bindParam(':id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->execute();
        $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user_info) {
            $user_name = htmlspecialchars($user_info['username']);
            if ($user_info['is_admin']) {
                $user_role = '管理者'; // adminの場合
            } else {
                $user_role = '一般ユーザー'; // adminではない場合
            }
            error_log("Header: User info found for ID " . $_SESSION['user_id'] . ". Username: " . $user_name . ", Role: " . $user_role);
        } else {
            error_log("Header: No user info found in DB for ID: " . $_SESSION['user_id']);
            // データベースにユーザーが見つからない場合、セッションをクリアする
            unset($_SESSION['user_id']);
            unset($_SESSION['user_name']);
            unset($_SESSION['user_email']);
        }
    } catch (PDOException $e) {
        error_log("Header DB error: " . $e->getMessage());
        // エラー時はロールを表示しない
        $user_role = null;
        $user_name = null;
    }
} else {
    error_log("Header: No user ID in session.");
}
?>

<header class="py-3 bg-primary text-white grid-header">
    <div class="container-fluid d-flex justify-content-between align-items-center">
        <!-- サイドバー切り替えボタン (モバイル用) -->
        <button class="btn btn-outline-light d-md-none me-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#myCustomSidebar" aria-controls="myCustomSidebar">
            <i class="fas fa-bars"></i>
        </button>
        <!-- サイドバー切り替えボタン (PC用) -->
        <button class="btn btn-outline-light me-3 d-none d-md-inline-flex" id="myCustomSidebarToggleBtn" type="button">
            <i class="fas fa-bars"></i>
        </button>
        <h3 class="my-0 me-auto">
            <a href="/" class="text-white text-decoration-none">Tiper Live</a>
        </h3>
        <nav class="navbar navbar-expand-md navbar-dark p-0">
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="/"><i class="fas fa-home me-1"></i>ホーム</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="fas fa-box me-1"></i>サービス</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-cube me-1"></i>製品
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="#">製品A</a></li>
                            <li><a class="dropdown-item" href="#">製品B</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#">その他</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="fas fa-question-circle me-1"></i>よくある質問</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="fas fa-envelope me-1"></i>お問い合わせ</a>
                    </li>
                </ul>
                <form class="d-none d-md-inline-flex ms-3" role="search">
                    <div class="input-group">
                        <input class="form-control" type="search" placeholder="サイト内検索..." aria-label="Search">
                        <button class="btn btn-outline-light" type="submit"><i class="fas fa-search"></i></button>
                    </div>
                </form>
                
                <!-- ログイン状態の表示とボタンの切り替え -->
                <div class="ms-3 d-flex align-items-center">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if ($user_name): ?>
                            <span class="text-white me-2">
                                <i class="fas fa-user-circle me-1"></i>
                                <?php echo $user_name; ?>
                                <?php if ($user_role): ?>
                                    (<?php echo $user_role; ?>)
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                        <a href="/logout.php" class="btn btn-outline-light">
                            <i class="fas fa-sign-out-alt me-1"></i>ログアウト
                        </a>
                    <?php else: ?>
                        <a href="/login.php" class="btn btn-outline-light">
                            <i class="fas fa-sign-in-alt me-1"></i>ログイン
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
    </div>
</header>
