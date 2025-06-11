<?php
// C:\doc\my_web_project\app\public\products_admin.php

// データベース接続設定ファイルを読み込む
require_once __DIR__ . '/../includes/db_config.php';

// このページは認証が必要な場合、ここでセッションチェックを行う
// if (!isset($_SESSION['user_id'])) {
//     header("Location: /login.php");
//     exit();
// }

// データベース接続
$pdo = connectDB();

// ここに商品の追加、編集、削除などのロジックを記述
// 例:
// if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_product') {
//     // 商品追加処理
//     // フォームからのデータを受け取り、バリデーション後、productsテーブルやrow_api_dataテーブルに挿入
// }

?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb bg-light p-3 rounded shadow-sm">
        <li class="breadcrumb-item"><a href="/"><i class="fas fa-home me-1"></i>ホーム</a></li>
        <li class="breadcrumb-item active" aria-current="page"><i class="fas fa-box me-1"></i>商品登録</li>
    </ol>
</nav>

<div class="container-fluid bg-white p-4 rounded shadow-sm mt-3">
    <h1 class="mb-4"><i class="fas fa-box me-2"></i>商品登録・管理</h1>

    <p>このページでは、商品の登録と管理を行います。将来的には、APIからの自動データ取得機能もここに統合できます。</p>

    <!-- ここに商品登録フォームや、商品一覧表示、APIデータ取り込みトリガーなどを配置 -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>新規商品登録</h5>
        </div>
        <div class="card-body">
            <form action="" method="POST">
                <div class="mb-3">
                    <label for="productTitle" class="form-label">商品タイトル</label>
                    <input type="text" class="form-control" id="productTitle" name="product_title" placeholder="商品名を入力してください" required>
                </div>
                <div class="mb-3">
                    <label for="releaseDate" class="form-label">リリース日</label>
                    <input type="date" class="form-control" id="releaseDate" name="release_date" required>
                </div>
                <div class="mb-3">
                    <label for="sourceApi" class="form-label">APIソース (例: fanza)</label>
                    <input type="text" class="form-control" id="sourceApi" name="source_api" placeholder="fanza, dmmなど" required>
                </div>
                <!-- 仮のAPIデータ入力欄。将来的にはAPIからの自動取得に置き換え -->
                <div class="mb-3">
                    <label for="rowData" class="form-label">JSONデータ (API / CSV変換後)</label>
                    <textarea class="form-control" id="rowData" name="row_data" rows="5" placeholder="APIから取得した生のJSONデータ、またはCSVをJSONに変換したデータを貼り付けてください"></textarea>
                </div>
                <button type="submit" name="action" value="add_product" class="btn btn-primary"><i class="fas fa-save me-2"></i>商品登録</button>
            </form>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>登録済み商品一覧</h5>
        </div>
        <div class="card-body">
            <p>ここに登録済み商品の一覧が表示されます。</p>
            <!-- 例: データベースから商品データを取得して表示 -->
            <?php
            // $stmt = $pdo->query("SELECT id, title, release_date FROM products ORDER BY created_at DESC LIMIT 10");
            // while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            //     echo "<p>" . htmlspecialchars($row['title']) . " (リリース日: " . htmlspecialchars($row['release_date']) . ")</p>";
            // }
            ?>
        </div>
    </div>
</div>
