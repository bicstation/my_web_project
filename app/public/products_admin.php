<?php
// C:\project\my_web_project\app\public\products_admin.php

// 共通初期化ファイルを読み込む（セッションハンドラ設定とsession_start()を含む）
// session_start(); // ★修正: init.phpで既にセッションが開始されているため、この行は削除します。
require_once __DIR__ . '/init.php'; // init.phpを読み込むことでsession_start()が実行されます

// データベース接続設定ファイルを読み込む
require_once __DIR__ . '/../includes/db_config.php';

// このページは認証が必要な場合、ここでセッションチェックを行う
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

// データベース接続
$pdo = connectDB();

$message = ""; // 成功・失敗メッセージを格納する変数

// -----------------------------------------------------
// Duga API CLIスクリプト実行トリガーの処理
// -----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'run_duga_api_cli') {
    // ログファイルのパス (CLIスクリプトが出力するログと同じ場所)
    // コンテナ内のパスに変換されることを想定
    $log_file_container_path = '/var/www/html/duga_api_processing.log';
    
    // CLIスクリプトのパス（Dockerコンテナ内のパス）
    $cli_script_path = '/var/www/html/cli/process_duga_api.php';
    
    // API検索条件の取得
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $keyword = $_POST['keyword'] ?? '';
    $genre_id = $_POST['genre_id'] ?? ''; // ジャンルIDの取得

    // ★修正: 新しいDuga API検索パラメータの取得
    // agentidのデフォルト値を数字の「48043」に変更
    $agentid = $_POST['agentid'] ?? '48043'; 
    $bannerid = $_POST['bannerid'] ?? '';
    $adult = $_POST['adult'] ?? '';
    $sort = $_POST['sort'] ?? '';

    // CLIスクリプトに渡す引数を構築
    $cli_arguments = [];
    if (!empty($start_date)) {
        $cli_arguments[] = '--start_date=' . escapeshellarg($start_date);
    }
    if (!empty($end_date)) {
        $cli_arguments[] = '--end_date=' . escapeshellarg($end_date);
    }
    if (!empty($keyword)) {
        $cli_arguments[] = '--keyword=' . escapeshellarg($keyword);
    }
    if (!empty($genre_id)) {
        $cli_arguments[] = '--genre_id=' . escapeshellarg($genre_id);
    }
    // ★修正: agentid が空でない場合のみ引数に追加 (空の場合はCLIスクリプトでデフォルト値が適用される)
    if (!empty($agentid)) { 
        $cli_arguments[] = '--agentid=' . escapeshellarg($agentid);
    }
    if (!empty($bannerid)) {
        $cli_arguments[] = '--bannerid=' . escapeshellarg($bannerid);
    }
    if (!empty($adult)) {
        $cli_arguments[] = '--adult=' . escapeshellarg($adult);
    }
    if (!empty($sort)) {
        $cli_arguments[] = '--sort=' . escapeshellarg($sort);
    }

    $arguments_string = implode(' ', $cli_arguments);
    
    // PHPコンテナ内で直接CLIスクリプトを実行するコマンドを構築
    $command = "nohup php " . escapeshellarg($cli_script_path) . " {$arguments_string} >> " . escapeshellarg($log_file_container_path) . " 2>&1 &";
    
    // Debugging: 実行されるコマンドをログに出力
    error_log("Attempting to execute Duga API CLI via shell_exec: " . $command);

    // コマンド実行
    $output = shell_exec($command);

    if ($output === null || $output === false) {
        $message = "<div class='alert alert-danger'>Duga API CLIスクリプトの実行リクエストに失敗しました。<br>詳細についてはサーバーログを確認してください。</div>";
        error_log("Failed to initiate Duga API CLI script. shell_exec output: " . print_r($output, true));
    } else {
        $message = "<div class='alert alert-success'>Duga APIからのデータ取得と保存処理をバックグラウンドで開始しました。<br>進行状況は `app/duga_api_processing.log` を確認してください。</div>";
        error_log("Duga API CLI script initiated successfully. shell_exec output: " . $output);
    }
}


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

    <?php
    // メッセージ表示エリア
    if (!empty($message)) {
        echo $message;
    }
    ?>

    <p>このページでは、商品の登録と管理を行います。APIからの自動データ取得機能もここに統合できます。</p>

    <!-- CLIスクリプト実行トリガー -->
    <div class="card mt-4 mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-sync-alt me-2"></i>Duga APIデータ取り込み</h5>
        </div>
        <div class="card-body">
            <p>Duga APIから最新のアイテムデータを取得し、データベースに保存します。この処理はバックグラウンドで実行されます。</p>
            <form action="" method="POST" onsubmit="return confirm('Duga APIからのデータ取り込みを開始しますか？大量のデータを処理するため時間がかかる場合があります。');">
                <input type="hidden" name="action" value="run_duga_api_cli">
                
                <!-- API検索条件入力フィールド -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="start_date" class="form-label">リリース日 (開始)</label>
                        <input type="date" class="form-control" id="start_date" name="start_date">
                    </div>
                    <div class="col-md-6">
                        <label for="end_date" class="form-label">リリース日 (終了)</label>
                        <input type="date" class="form-control" id="end_date" name="end_date">
                    </div>
                </div>
                <div class="mb-3">
                    <label for="keyword" class="form-label">キーワード</label>
                    <input type="text" class="form-control" id="keyword" name="keyword" placeholder="例: アクション">
                </div>
                <div class="mb-3">
                    <label for="genre_id" class="form-label">ジャンルID</label>
                    <input type="text" class="form-control" id="genre_id" name="genre_id" placeholder="例: 101 (APIドキュメントを参照)">
                    <small class="form-text text-muted">複数のジャンルIDを指定する場合はカンマ区切り (例: 101,102)</small>
                </div>
                <!-- ★修正: 新しいDuga API検索条件入力フィールド -->
                <div class="mb-3">
                    <label for="agentid" class="form-label">Agent ID</label>
                    <input type="text" class="form-control" id="agentid" name="agentid" value="48043" placeholder="例: 48043">
                </div>
                <div class="mb-3">
                    <label for="bannerid" class="form-label">Banner ID</label>
                    <input type="text" class="form-control" id="bannerid" name="bannerid" placeholder="例: 01">
                </div>
                <div class="mb-3">
                    <label for="adult" class="form-label">成人向けコンテンツ (0/1)</label>
                    <input type="text" class="form-control" id="adult" name="adult" placeholder="例: 1">
                    <small class="form-text text-muted">0: 一般向け, 1: 成人向け</small>
                </div>
                <div class="mb-3">
                    <label for="sort" class="form-label">ソート順</label>
                    <input type="text" class="form-control" id="sort" name="sort" placeholder="例: favorite, rank, date">
                    <small class="form-text text-muted">APIドキュメントで有効なソート順を確認してください。</small>
                </div>

                <button type="submit" class="btn btn-warning"><i class="fas fa-cloud-download-alt me-2"></i>Duga APIデータ取り込み実行</button>
            </form>
            <small class="text-muted mt-2 d-block">
                <strong>注意:</strong> この機能は開発環境でのテスト目的です。本番環境では、専用のバッチ処理システムやキューイングシステムを介して実行することを強く推奨します。
                詳細なログは `app/duga_api_processing.log` で確認できます。
            </small>
        </div>
    </div>


    <!-- ここに商品登録フォームや、商品一覧表示などを配置 -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>新規商品登録 (手動)</h5>
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
            <?php
            // 例: データベースから商品データを取得して表示
            // $stmt = $pdo->query("SELECT id, title, release_date, source_api FROM products ORDER BY created_at DESC LIMIT 10");
            // if ($stmt) {
            //     echo "<table class='table table-bordered table-striped'>";
            //     echo "<thead><tr><th>ID</th><th>タイトル</th><th>リリース日</th><th>ソース</th></tr></thead>";
            //     echo "<tbody>";
            //     while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            //     echo "<tr>";
            //     echo "<td>" . htmlspecialchars($row['id']) . "</td>";
            //     echo "<td>" . htmlspecialchars($row['title']) . "</td>";
            //     echo "<td>" . htmlspecialchars($row['release_date']) . "</td>";
            //     echo "<td>" . htmlspecialchars($row['source_api']) . "</td>";
            //     echo "</tr>";
            //     }
            //     echo "</tbody></table>";
            // } else {
            //     echo "<p class='alert alert-info'>まだ商品が登録されていません。</p>";
            // }
            ?>
        </div>
    </div>
</div>
