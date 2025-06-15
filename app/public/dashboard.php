<?php
// C:\project\my_web_project\app\public\dashboard.php

// index.php からインクルードされることを想定しているため、
// データベース接続やセッションの開始はここで改めて行う必要はありません。
// それらは index.php で既に処理されています。

// App\Core\Session クラスが利用可能であることを前提とします
use App\Core\Session;

// ユーザー情報の取得
$userId = Session::getUserId(); // 'user_id' キーで取得
$username = Session::getUsername(); // 'user_username' キーで取得 (Sessionクラスで定義)
$userRole = Session::getUserRole(); // 'user_role' キーで取得

// Debugging: セッションに何が格納されているか確認 (本番環境では削除またはコメントアウト)
// error_log("Dashboard: Session data: " . print_r($_SESSION, true));

?>
<!-- パンくずリスト -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb bg-light p-3 rounded shadow-sm">
        <li class="breadcrumb-item"><a href="/"><i class="fas fa-home me-1"></i>ホーム</a></li>
        <li class="breadcrumb-item active" aria-current="page"><i class="fas fa-chart-line me-1"></i>ダッシュボード</li>
    </ol>
</nav>

<!-- メインコンテンツカード -->
<div class="container-fluid bg-white p-4 rounded shadow-sm mt-3">
    <h1 class="mb-4"><i class="fas fa-tachometer-alt me-2"></i>ダッシュボード</h1>
    
    <!-- ログイン成功メッセージ -->
    <div class="alert alert-info" role="alert">
        ようこそ、<strong><?php echo htmlspecialchars($username ?? 'ゲスト'); ?></strong>さん！
        これはログイン後にのみアクセスできるダッシュボードページです。
    </div>

    <!-- ユーザー情報とクイック統計 -->
    <div class="row mt-4">
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-primary text-white rounded-top">
                    <h5 class="card-title mb-0"><i class="fas fa-user-circle me-2"></i>ユーザー情報</h5>
                </div>
                <div class="card-body">
                    <p><strong>ユーザーID:</strong> <?php echo htmlspecialchars($userId ?? 'N/A'); ?></p>
                    <p><strong>ユーザー名:</strong> <?php echo htmlspecialchars($username ?? 'N/A'); ?></p>
                    <p><strong>役割:</strong> <?php echo htmlspecialchars($userRole ?? 'N/A'); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-success text-white rounded-top">
                    <h5 class="card-title mb-0"><i class="fas fa-chart-bar me-2"></i>クイック統計</h5>
                </div>
                <div class="card-body">
                    <p>ここにサイトの統計や重要な情報が表示されます。</p>
                    <ul>
                        <li>総ユーザー数: <strong>[動的に取得]</strong></li>
                        <li>総商品数: <strong>[動的に取得]</strong></li>
                        <li>本日の売上: <strong>[動的に取得]</strong></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- アクションと設定 -->
    <h3 class="mt-5 mb-3"><i class="fas fa-cogs me-2"></i>アクションと設定</h3>
    <div class="row">
        <div class="col-md-4 mb-3">
            <a href="index.php?page=profile" class="card-link text-decoration-none">
                <div class="card text-center p-3 h-100 d-flex flex-column justify-content-center align-items-center">
                    <i class="fas fa-user-edit fa-3x text-info mb-2"></i>
                    <h6 class="card-subtitle mb-0">プロフィール編集</h6>
                </div>
            </a>
        </div>
        <?php if ($userRole === 'admin'): // 管理者のみ表示 ?>
        <div class="col-md-4 mb-3">
            <a href="index.php?page=users_admin" class="card-link text-decoration-none">
                <div class="card text-center p-3 h-100 d-flex flex-column justify-content-center align-items-center">
                    <i class="fas fa-users-cog fa-3x text-warning mb-2"></i>
                    <h6 class="card-subtitle mb-0">ユーザー管理</h6>
                </div>
            </a>
        </div>
        <div class="col-md-4 mb-3">
            <a href="index.php?page=products_admin" class="card-link text-decoration-none">
                <div class="card text-center p-3 h-100 d-flex flex-column justify-content-center align-items-center">
                    <i class="fas fa-box-open fa-3x text-danger mb-2"></i>
                    <h6 class="card-subtitle mb-0">商品管理</h6>
                </div>
            </a>
        </div>
        <?php endif; ?>
    </div>

    <hr class="my-5">

    <!-- 最近のアクティビティ -->
    <h3 class="mb-3"><i class="fas fa-history me-2"></i>最近のアクティビティ</h3>
    <div class="card">
        <ul class="list-group list-group-flush">
            <li class="list-group-item">2023-10-26 10:30 - プロフィールを更新しました。</li>
            <li class="list-group-item">2023-10-25 15:45 - 新しい商品「サンプル商品A」を追加しました。</li>
            <li class="list-group-item">2023-10-24 09:00 - ログインしました。</li>
        </ul>
    </div>

    <!-- ログアウトボタン -->
    <form action="index.php?page=logout" method="post" class="mt-5 text-center">
        <!-- ログアウトフォームにもCSRFトークンを含めることを推奨 -->
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(Session::get('csrf_token')); ?>">
        <button type="submit" class="btn btn-danger btn-lg rounded-pill shadow-sm">
            <i class="fas fa-sign-out-alt me-2"></i>ログアウト
        </button>
    </form>
</div>
