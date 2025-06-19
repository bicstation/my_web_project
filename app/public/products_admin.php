<?php
// C:\project\my_web_project\app\public\products_admin.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Composerのオートローダーを読み込む
// これにより、App名前空間下のクラスや、vlucas/phpdotenvなどのComposerが管理するライブラリが自動的にロードされます。
require_once __DIR__ . '/../../vendor/autoload.php';

// Dotenvライブラリを使って.envファイルをロード
// プロジェクトルートにある .env ファイルを指す
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// 名前空間を使用するクラスをインポート
use App\Core\Logger;
use App\Core\Database;
use App\Api\DugaApiClient;
use App\Util\DbBatchInsert; // 必要に応じてDbBatchInsertもインポート

// 共通初期化ファイルを読み込む（セッションハンドラ設定とsession_start()を含む）
// init.php 内で Composer のオートローダーを読み込んだり、.env をロードしたりする必要はなくなります。
// init.php は主にセッション開始、認証チェックなどの初期化処理に専念します。
// require_once __DIR__ . '/init.php'; // この行はコメントアウトまたは削除された状態を維持します。

// データベース接続設定を.envから取得
$dbConfig = [
    'host'      => $_ENV['DB_HOST'] ?? 'localhost',
    'dbname'    => $_ENV['DB_NAME'] ?? 'tiper', // ★修正: web_project_db から tiper に変更
    'user'      => $_ENV['DB_USER'] ?? 'root',
    'pass'      => $_ENV['DB_PASS'] ?? 'password',
    'charset'   => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
];

// このページは認証が必要な場合、ここでセッションチェックを行う
// (セッションが開始されていることを前提とする。必要に応じて session_start() を追加)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

// -----------------------------------------------------
// 初期設定とリソースの準備 (クラスのインスタンス化)
// -----------------------------------------------------
$logger = null;
$database = null;
$dugaApiClient = null;
$dbBatchInserter = null; // 必要であればインスタンス化

try {
    // Loggerクラスのインスタンス化
    // ログファイルのパスを現在のプロジェクト構成に合わせて調整
    $logger = new Logger(__DIR__ . '/../logs/products_admin_processing.log'); // 管理画面専用のログファイル
    $logger->log("products_admin.php ページアクセス処理を開始します。");

    // データベース接続の確立
    $database = new Database($dbConfig, $logger);
    $pdo = $database->getConnection(); // PDOインスタンスを取得

    // Duga APIクライアントのインスタンス化
    $dugaApiUrl = $_ENV['DUGA_API_URL'] ?? 'http://affapi.duga.jp/search'; // .envと合わせる
    $dugaApiKey = $_ENV['DUGA_API_KEY'] ?? 'YOUR_DUGA_API_KEY_HERE';
    $dugaApiClient = new DugaApiClient($dugaApiUrl, $dugaApiKey, $logger);

    // DbBatchInsertヘルパークラスのインスタンス化 (手動登録で必要になるかも)
    $dbBatchInserter = new DbBatchInsert($database, $logger);


} catch (Exception $e) {
    // 初期設定段階でのエラーをログに記録し、スクリプトを終了
    error_log("Webページ初期設定エラー: " . $e->getMessage()); // PHPのエラーログに出力
    if ($logger) {
        $logger->error("Webページ初期設定中に致命的なエラーが発生しました: " . htmlspecialchars($e->getMessage()));
    }
    // エラーメッセージをユーザーに表示
    $message = "<div class='alert alert-danger'>初期設定中にエラーが発生しました。ログを確認してください。<br>" . htmlspecialchars($e->getMessage()) . "</div>";
    // 以降の処理は実行しない
    die($message);
}

$message = ""; // 成功・失敗メッセージを格納する変数 (初期化をtry-catch後に移動)


// -----------------------------------------------------
// Duga API CLIスクリプト実行トリガーの処理
// -----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'run_duga_api_cli') {
    // ログファイルのパス (CLIスクリプトが出力するログと同じ場所)
    // コンテナ内のパスに変換されることを想定
    $log_file_container_path = '/var/www/html/app/logs/duga_api_processing.log'; // CLIスクリプトのログパスと一致させる

    // CLIスクリプトのパス（Dockerコンテナ内のパス）
    // プロジェクトルートが /var/www/html にマウントされているため、正しいパスは /var/www/html/app/cli/process_duga_api.php
    $cli_script_path = '/var/www/html/app/cli/process_duga_api.php';
    
    // API検索条件の取得
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $keyword = $_POST['keyword'] ?? '';
    $genre_id = $_POST['genre_id'] ?? ''; // ジャンルIDの取得

    $agentid = $_POST['agentid'] ?? ''; // CLIスクリプトに任せる場合は空でよい
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
    // agentid がフォームで指定されていれば追加 (CLIスクリプトのデフォルト値を上書き)
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
    // nohup はバックグラウンドでプロセスを実行し、ログをリダイレクトします。
    $command = "nohup php " . escapeshellarg($cli_script_path) . " {$arguments_string} >> " . escapeshellarg($log_file_container_path) . " 2>&1 &";
    
    // Debugging: 実行されるコマンドをログに出力
    $logger->log("Attempting to execute Duga API CLI via shell_exec: " . $command);

    // コマンド実行
    $output = shell_exec($command); // shell_exec はコマンドの標準出力のみを返します。バックグラウンド実行では通常空です。

    // shell_exec が 'false' を返した場合のみ、コマンドの実行開始自体に失敗したと判断します。
    // 'null' や空文字列は、コマンドがバックグラウンドで起動し、即座に標準出力がなかったことを意味します。
    if ($output === false) {
        $message = "<div class='alert alert-danger'>Duga API CLIスクリプトの実行リクエストに失敗しました。<br>このサーバー環境では、`php`コマンドまたは`shell_exec`の実行権限に問題がある可能性があります。詳細はサーバーログを確認してください。</div>";
        $logger->error("Failed to execute Duga API CLI command (shell_exec returned false). Command: '{$command}'");
    } else {
        // コマンドが起動できたと判断し、成功メッセージを表示
        $message = "<div class='alert alert-success'>Duga APIからのデータ取得と保存処理をバックグラウンドで開始しました。<br>進行状況は `app/logs/duga_api_processing.log` を確認してください。</div>";
        $logger->log("Duga API CLI script initiated (command sent to shell). Output (if any): " . ($output ?? 'NULL')); // nullの場合も明示的にログに記録
    }
}


// ここに商品の追加、編集、削除などのロジックを記述
// 例: 手動商品登録の処理 (DbBatchInsert を使用)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_product') {
    $product_title = $_POST['product_title'] ?? '';
    $release_date = $_POST['release_date'] ?? '';
    $source_api = $_POST['source_api'] ?? '';
    $row_data = $_POST['row_data'] ?? ''; // JSONデータ

    if (empty($product_title) || empty($release_date) || empty($source_api)) {
        $message = "<div class='alert alert-danger'>商品タイトル、リリース日、APIソースは必須です。</div>";
        $logger->error("手動商品登録失敗: 必須フィールドが空です。");
    } else {
        try {
            // raw_api_data に挿入
            $raw_api_data_entry = [
                'source_api'     => $source_api, // source_name から source_api に修正
                'product_id'     => uniqid('manual_'), // api_product_id から product_id に修正
                'api_response_data' => $row_data, // row_json_data から api_response_data に修正
                'fetched_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s')
            ];
            // insertOrUpdate メソッドは DbBatchInsert に存在すると仮定
            // products_admin.phpは単一のデータ挿入なので、直接PDO::prepare/executeを使用する方がシンプル
            $stmt_raw = $pdo->prepare("
                INSERT INTO raw_api_data (source_api, product_id, api_response_data, fetched_at, updated_at)
                VALUES (:source_api, :product_id, :api_response_data, :fetched_at, :updated_at)
            ");
            $stmt_raw->execute([
                ':source_api'        => $raw_api_data_entry['source_api'],
                ':product_id'        => $raw_api_data_entry['product_id'],
                ':api_response_data' => $raw_api_data_entry['api_response_data'],
                ':fetched_at'        => $raw_api_data_entry['fetched_at'],
                ':updated_at'        => $raw_api_data_entry['updated_at'],
            ]);
            $raw_api_data_id = $pdo->lastInsertId();
            
            if ($raw_api_data_id) {
                // products テーブルに挿入
                $product_entry = [
                    'product_id'    => $raw_api_data_entry['product_id'], // raw_api_data と同じIDを使用
                    'title'         => $product_title,
                    'release_date'  => $release_date,
                    'maker_name'    => null, // 手動登録ではmaker_nameやgenreは空または別途入力フィールドが必要
                    'genre'         => null,
                    'url'           => null,
                    'image_url'     => null,
                    'source_api'    => $source_api,
                    'row_api_data_id' => $raw_api_data_id,
                    'created_at'    => date('Y-m-d H:i:s'),
                    'updated_at'    => date('Y-m-d H:i:s')
                ];
                $stmt_products = $pdo->prepare("
                    INSERT INTO products (product_id, title, release_date, maker_name, genre, url, image_url, source_api, row_api_data_id, created_at, updated_at)
                    VALUES (:product_id, :title, :release_date, :maker_name, :genre, :url, :image_url, :source_api, :row_api_data_id, :created_at, :updated_at)
                    ON DUPLICATE KEY UPDATE
                        title = VALUES(title),
                        release_date = VALUES(release_date),
                        maker_name = VALUES(maker_name),
                        genre = VALUES(genre),
                        url = VALUES(url),
                        image_url = VALUES(image_url),
                        source_api = VALUES(source_api),
                        updated_at = VALUES(updated_at)
                ");
                $stmt_products->execute($product_entry); // 配列を直接渡す

                $message = "<div class='alert alert-success'>商品「" . htmlspecialchars($product_title) . "」が正常に登録されました。</div>";
                $logger->log("手動商品登録成功: " . $product_title);
            } else {
                $message = "<div class='alert alert-danger'>手動商品登録に失敗しました: raw_api_data のIDを取得できませんでした。</div>";
                $logger->error("手動商品登録失敗: raw_api_data のIDが見つかりません。");
            }

        } catch (Exception $e) {
            $message = "<div class='alert alert-danger'>商品登録中にエラーが発生しました: " . htmlspecialchars($e->getMessage()) . "</div>";
            $logger->error("手動商品登録中にエラー: " . htmlspecialchars($e->getMessage()));
        }
    }
}
// -----------------------------------------------------
// 商品一覧の表示ロジック (DbBatchInsertを使用しない)
// -----------------------------------------------------
$products = [];
try {
    $stmt = $pdo->query("SELECT id, product_id, title, release_date, source_api FROM products ORDER BY created_at DESC LIMIT 20");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $logger->error("商品一覧の取得中にエラーが発生しました: " . htmlspecialchars($e->getMessage()));
    $message = "<div class='alert alert-danger'>商品一覧の取得中にエラーが発生しました。データベース接続またはproductsテーブルのスキーマを確認してください。</div>"; // より具体的なメッセージに修正
}

?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>商品登録・管理</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome (アイコン用) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }
        .container-fluid {
            padding: 30px;
            border-radius: 8px;
        }
        .card {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
        }
        .card-header {
            background-color: #007bff;
            color: white;
            font-weight: bold;
            padding: 15px 20px;
        }
        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #212529;
            font-weight: bold;
            border-radius: 5px;
            padding: 10px 20px;
            transition: background-color 0.2s ease, border-color 0.2s ease;
        }
        .btn-warning:hover {
            background-color: #e0a800;
            border-color: #d39e00;
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            font-weight: bold;
            border-radius: 5px;
            padding: 10px 20px;
            transition: background-color 0.2s ease, border-color 0.2s ease;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        .alert {
            border-radius: 5px;
            padding: 15px;
            margin-top: 15px;
        }
        .breadcrumb {
            background-color: #e9ecef;
            border-radius: 0.25rem;
        }
        .breadcrumb-item a {
            color: #007bff;
            text-decoration: none;
        }
        .breadcrumb-item a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="container-fluid">
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
                    <!-- 新しいDuga API検索条件入力フィールド -->
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
                    詳細なログは `app/logs/duga_api_processing.log` で確認できます。
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
                    <input type="hidden" name="action" value="add_product">
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
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>商品登録</button>
                </form>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>登録済み商品一覧</h5>
            </div>
            <div class="card-body">
                <p>登録済み商品の一覧:</p>
                <?php if (!empty($products)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>製品ID</th>
                                <th>タイトル</th>
                                <th>リリース日</th>
                                <th>ソースAPI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?= htmlspecialchars($product['id']) ?></td>
                                <td><?= htmlspecialchars($product['product_id']) ?></td>
                                <td><?= htmlspecialchars($product['title']) ?></td>
                                <td><?= htmlspecialchars($product['release_date']) ?></td>
                                <td><?= htmlspecialchars($product['source_api']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class='alert alert-info'>まだ商品が登録されていません。</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle (Popper.jsを含む) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
