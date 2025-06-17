<?php
// C:\project\my_web_project\app\cli\process_duga_api.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "--- スクリプト実行開始（強制エラー表示ON） ---\n";

// Composerのオートローダーを読み込む
require_once __DIR__ . '/../../vendor/autoload.php';

// Dotenvライブラリを使って.envファイルをロード
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// 名前空間を使用するクラスをインポート
use App\Core\Logger;
use App\Core\Database;
use App\Api\DugaApiClient;
use App\Util\DbBatchInsert;
// use PDOException; // PDOExceptionをuseしておく

// このスクリプトがCLI (コマンドラインインターフェース) から実行されたことを確認
if (php_sapi_name() !== 'cli') {
    die("このスクリプトはWebブラウザからではなく、CLI (コマンドライン) から実行してください。\n");
}

// -----------------------------------------------------
// 定義: 設定値とデフォルト値 (大部分は.envから取得される)
// -----------------------------------------------------
const DEFAULT_AGENT_ID = '48043';
const DEFAULT_ADULT_PARAM = '1';
const DEFAULT_SORT_PARAM = 'favorite';
const DEFAULT_BANNER_ID = '01';
// Duga APIが一度に返すレコード数 (APIのドキュメントを確認し、最大値を設定すること。通常100〜500)
const API_RECORDS_PER_REQUEST = 100; // 例: Duga APIのhitsパラメーターの上限
const DB_BUFFER_SIZE = 500;          // データベースへのバッチ処理のチャンクサイズ
const API_SOURCE_NAME = 'duga';      // このAPIのソース名
// APIリクエストの連続失敗回数がこの値に達したら、本当に終了と判断する閾値
const MAX_CONSECUTIVE_EMPTY_RESPONSES = 5; 

// .env から Duga API の設定を取得
$dugaApiUrl = $_ENV['DUGA_API_URL'] ?? 'http://affapi.duga.jp/search';
$dugaApiKey = $_ENV['DUGA_API_KEY'] ?? 'YOUR_DUGA_API_KEY_HERE';

// .env からデータベース設定を取得
$dbConfig = [
    'host'      => $_ENV['DB_HOST'] ?? 'localhost',
    'dbname'    => $_ENV['DB_NAME'] ?? 'tiper',
    'user'      => $_ENV['DB_USER'] ?? 'root',
    'pass'      => $_ENV['DB_PASS'] ?? 'password',
    'charset'   => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
];


// -----------------------------------------------------
// 初期設定とリソースの準備
// -----------------------------------------------------
$logger = null;
$database = null;
$dugaApiClient = null;
$dbBatchInserter = null;

// ロックファイルのパス (一時ファイルとして設定)
// Docker環境の場合、コンテナ内で共通してアクセスできるパスが望ましい
$lockFile = '/tmp/process_duga_api.lock'; 

try {
    // ログファイルのパスを現在の構成に合わせて調整
    $logger = new Logger(__DIR__ . '/../logs/duga_api_processing.log'); 
    $logger->log("Duga APIから生データの取得とraw_api_dataテーブルへの保存処理を開始します。");

    // ★ ロックファイルのチェックと作成 ★
    if (file_exists($lockFile)) {
        $pid = file_get_contents($lockFile);
        $logger->error("別のインスタンスが既に実行中です (PID: {$pid})。スクリプトを終了します。");
        die("エラー: 別のインスタンスが既に実行中です。スクリプトを終了します。\n");
    }
    file_put_contents($lockFile, getmypid()); // プロセスIDを書き込む
    register_shutdown_function(function() use ($lockFile, $logger) {
        if (file_exists($lockFile)) {
            unlink($lockFile);
            $logger->log("ロックファイルを削除しました。");
        }
    });
    $logger->log("スクリプトロックファイルを作成しました: {$lockFile}");
    // ★ ロックファイルのチェックと作成 ここまで ★

    $database = new Database($dbConfig, $logger);
    $pdo = $database->getConnection(); // PDOインスタンスを取得

    $dugaApiClient = new DugaApiClient($dugaApiUrl, $dugaApiKey, $logger);

    $dbBatchInserter = new DbBatchInsert($database, $logger);

} catch (Exception $e) {
    error_log("CLI初期設定エラー: " . $e->getMessage());
    if ($logger) {
        $logger->error("CLI初期設定中に致命的なエラーが発生しました: " . $e->getMessage());
    }
    // エラー発生時もロックファイルを削除する（register_shutdown_functionで対応済みだが念のため）
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
    die("エラー: CLIスクリプトの初期設定中に問題が発生しました。詳細はサーバログを確認してください。\n");
}

// -----------------------------------------------------
// コマンドライン引数のパース
// -----------------------------------------------------
$cli_options = getopt("", ["start_date::", "end_date::", "keyword::", "genre_id::", "agentid::", "bannerid::", "adult::", "sort::"]);

$start_date = $cli_options['start_date'] ?? null;
$end_date   = $cli_options['end_date'] ?? null;
$keyword    = $cli_options['keyword'] ?? null;
$genre_id   = $cli_options['genre_id'] ?? null;
$agentid    = $cli_options['agentid'] ?? DEFAULT_AGENT_ID;
$bannerid   = $cli_options['bannerid'] ?? DEFAULT_BANNER_ID;
$adult      = $cli_options['adult'] ?? DEFAULT_ADULT_PARAM;
$sort       = $cli_options['sort'] ?? DEFAULT_SORT_PARAM;

$logger->log("CLI Arguments processed: " . json_encode([
    'start_date' => $start_date,
    'end_date' => $end_date,
    'keyword' => $keyword,
    'genre_id' => $genre_id,
    'agentid' => $agentid,
    'bannerid' => $bannerid,
    'adult' => $adult,
    'sort' => $sort
]));


// -----------------------------------------------------
// データ取得と保存のメインロジック
// -----------------------------------------------------
$current_offset = 1; // Duga APIの offset は1から始まる
$total_processed_records = 0; // 全体の処理済みレコード数
$raw_data_buffer = []; // raw_api_data テーブルに挿入するためのバッファ

// APIから取得すべき総件数 (初回APIコールで設定される)
$total_api_results = -1; // 初期値は-1。初回リクエストで設定されるまでループを継続

// APIリクエストの連続失敗カウンター
$consecutive_empty_responses = 0;

try {
    while (true) { // 無限ループにし、内部で終了条件を厳密にチェック
        $logger->log("Duga APIからアイテムを取得中... (offset: {$current_offset}, 件数: " . API_RECORDS_PER_REQUEST . ")");

        $additional_api_params = [];
        if ($start_date) $additional_api_params['release_date_from'] = $start_date;
        if ($end_date)   $additional_api_params['release_date_to'] = $end_date;
        if ($keyword)    $additional_api_params['keyword'] = $keyword;
        if ($genre_id)   $additional_api_params['genre_id'] = $genre_id;
        if ($agentid)    $additional_api_params['agentid'] = $agentid;
        if ($bannerid)   $additional_api_params['bannerid'] = $bannerid;
        if ($adult)      $additional_api_params['adult'] = $adult;
        if ($sort)       $additional_api_params['sort'] = $sort;

        // Duga APIからアイテムデータを取得 (DugaApiClientクラスを使用)
        // ここで DugaApiClient::getItems が ['items' => [...], 'count' => N] を返すことを期待
        $api_response = $dugaApiClient->getItems($current_offset, API_RECORDS_PER_REQUEST, $additional_api_params);
        $api_data_batch = $api_response['items'] ?? [];
        // DugaApiClientの変更に合わせて 'total_hits' を 'count' に修正
        $current_total_hits = $api_response['count'] ?? 0; 

        // 初回リクエスト時、または total_api_results がまだ設定されていない場合に設定
        if ($total_api_results === -1) {
            $total_api_results = $current_total_hits;
            $logger->log("Duga APIから報告された検索結果総数: {$total_api_results}件");
            if ($total_api_results === 0) {
                $logger->log("検索結果が0件のため、処理を終了します。");
                break; // 0件ならループを抜ける
            }
        }
        
        // 取得したレコード数に基づいて、連続空レスポンスカウンターを更新
        if (empty($api_data_batch)) {
            $consecutive_empty_responses++;
            $logger->warning("警告: Duga APIから空のアイテムデータが返されました。連続空レスポンス数: {$consecutive_empty_responses} (offset: {$current_offset})");
            
            // 連続空レスポンス数が閾値に達した場合、または総件数を超過しそうな場合は終了
            // もしAPIがこれ以上データを持っていないと判断できる場合は、ここで終了
            if ($consecutive_empty_responses >= MAX_CONSECUTIVE_EMPTY_RESPONSES || $total_processed_records >= $total_api_results) {
                 $logger->log("連続で空のAPIレスポンスが続いたため、またはAPIの総件数に達した/超過したため、処理を終了します。");
                 break; // ループを抜ける
            }
            // 空のレスポンスでもオフセットは進めるべき。同じオフセットで無限にリトライするのを防ぐ。
            $current_offset++; 
            sleep(1); // 短い休憩を挟むことで、レートリミットを回避できる可能性
            continue; // 次のオフセットで次のループへ
        } else {
            $consecutive_empty_responses = 0; // データが正常に取得できた場合、連続空レスポンスカウンターをリセット
        }

        // 取得したAPIデータをraw_api_dataのバッファに準備
        foreach ($api_data_batch as $api_record_wrapper) {
            $api_record = $api_record_wrapper['item'] ?? null;

            if (empty($api_record)) {
                $logger->error("警告: 'item' キーが見つからないか空のためレコードをスキップします: " . json_encode($api_record_wrapper));
                continue;
            }

            $content_id = $api_record['productid'] ?? null;
            
            if (empty($content_id)) {
                $logger->error("警告: productid が空のためレコードをスキップします: " . json_encode($api_record));
                continue; // productid がないレコードはスキップ
            }

            // raw_api_data テーブル用のデータ準備
            $raw_data_entry = [
                'source_name'    => API_SOURCE_NAME,
                'api_product_id' => $content_id,
                'row_json_data'  => json_encode($api_record),
                'fetched_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s')
            ];
            $raw_data_buffer[] = $raw_data_entry;
        } // foreach ($api_data_batch as $api_record_wrapper) 終了

        // バッファが指定したチャンクサイズに達したら、データベースへのUPSERTを実行
        if (count($raw_data_buffer) >= DB_BUFFER_SIZE) {
            $logger->log("raw_api_dataバッファが" . DB_BUFFER_SIZE . "件に達しました。データベースへのUPSERTを開始します。");
            
            // トランザクション開始
            $pdo->beginTransaction();
            try {
                // raw_api_data テーブルへのUPSERT
                // 'api_product_id' と 'source_name' の組み合わせでユニークと仮定し、重複したら更新
                $raw_data_upsert_columns = ['row_json_data', 'fetched_at', 'updated_at'];
                $dbBatchInserter->insertOrUpdate('raw_api_data', $raw_data_buffer, $raw_data_upsert_columns);
                $logger->log(count($raw_data_buffer) . "件の生データを 'raw_api_data' にUPSERTしました。");

                $pdo->commit();
                $logger->log("トランザクションをコミットしました。");
            } catch (PDOException $e) {
                $pdo->rollBack();
                $logger->error("データベースへのバッチUPSERT中にエラーが発生しました。トランザクションをロールバックしました: " . $e->getMessage());
                // このエラーが発生した場合、処理を継続できない可能性が高いため、スクリプトを終了
                die("致命的なデータベースエラーが発生しました。スクリプトを終了します。\n");
            }

            // バッファをクリア
            $raw_data_buffer = [];
        }

        $total_processed_records += count($api_data_batch); // 実際に処理したレコード数を加算
        $current_offset++; // 次のオフセットへ
        
        // 全件取得が完了したかチェック
        // APIから返された総件数より多くのレコードを処理した、
        // または、次に取得すべきオフセットが総件数を超過する場合、ループを抜ける
        // (total_api_results はヒット数なので、offset * hits > total_hits で終了)
        if (($current_offset - 1) * API_RECORDS_PER_REQUEST >= $total_api_results && $total_api_results !== 0) {
            $logger->log("APIから報告された全てのレコード ({$total_api_results}件) の取得が完了しました。総処理レコード数: {$total_processed_records}件");
            break; // ループを抜ける
        }

        // APIリクエスト間のインターバル (任意)
        // Duga APIのレートリミットを考慮して適宜調整
        usleep(200000); // 200ミリ秒 (0.2秒) 程度の待機
    } // while (true) ループ終了

    // ループ終了後、残っているバッファがあれば最後にUPSERT
    if (!empty($raw_data_buffer)) {
        $logger->log("残りのraw_api_dataバッファ " . count($raw_data_buffer) . " 件をデータベースにUPSERTします。");
        $pdo->beginTransaction();
        try {
            $raw_data_upsert_columns = ['row_json_data', 'fetched_at', 'updated_at'];
            $dbBatchInserter->insertOrUpdate('raw_api_data', $raw_data_buffer, $raw_data_upsert_columns);
            $logger->log(count($raw_data_buffer) . "件の生データを 'raw_api_data' にUPSERTしました。");
            $pdo->commit();
            $logger->log("トランザクションをコミットしました。");
        } catch (PDOException $e) {
            $pdo->rollBack();
            $logger->error("最終バッチのデータベースUPSERT中にエラーが発生しました。トランザクションをロールバックしました: " . $e->getMessage());
            die("致命的なデータベースエラーが発生しました。スクリプトを終了します。\n");
        }
        $raw_data_buffer = [];
    }

    $logger->log("Duga APIからの生データ取得とデータベース保存処理が完了しました。総処理レコード数: {$total_processed_records}件");

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) { // $pdoがnullでないことを確認
        $pdo->rollBack();
        $logger->error("予期せぬエラー発生によりトランザクションをロールバックしました。");
    }
    $logger->error("致命的なエラーが発生しました: " . $e->getMessage());
    die("エラー: 処理中に問題が発生しました。詳細はログを確認してください。\n");
} finally {
    if ($database) {
        $database->closeConnection();
    }
    $logger->log("データベース接続を閉じました。スクリプトを終了します。");
}

echo "--- スクリプト実行終了 ---\n";
?>