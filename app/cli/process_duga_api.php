<?php
// C:\project\my_web_project\app\cli\process_duga_api.php

// Composerのオートローダーを読み込む
// これにより、すべてのApp名前空間下のクラスが自動的にロードされるようになります。
require_once __DIR__ . '/../../vendor/autoload.php';

// Dotenvライブラリを使って.envファイルをロード
// MY_WEB_PROJECT のルートにある .env ファイルを指す
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// 名前空間を使用するクラスをインポート
use App\Core\Logger;
use App\Core\Database;
use App\Api\DugaApiClient;
use App\Util\DbBatchInsert;
use PDOException; // PDO関連の例外をキャッチするために追加

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
const API_RECORDS_PER_REQUEST = 100; // Duga APIが一度に返すレコード数
const DB_BUFFER_SIZE = 500;          // データベースへのバッチ処理のチャンクサイズ
const API_SOURCE_NAME = 'duga';     // このAPIのソース名

// .env から Duga API の設定を取得
// 環境変数がない場合のデフォルト値を設定することも可能
$dugaApiUrl = $_ENV['DUGA_API_URL'] ?? 'https://api.duga.jp/v1/'; // 実際のDuga APIのURLを設定
$dugaApiKey = $_ENV['DUGA_API_KEY'] ?? 'YOUR_DUGA_API_KEY_HERE'; // 実際のDuga APIキーを設定

// .env からデータベース設定を取得
$dbConfig = [
    'host'    => $_ENV['DB_HOST'] ?? 'localhost',
    'dbname'  => $_ENV['DB_NAME'] ?? 'web_project_db',
    'user'    => $_ENV['DB_USER'] ?? 'root',
    'pass'    => $_ENV['DB_PASS'] ?? 'password',
    'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
];


// -----------------------------------------------------
// 初期設定とリソースの準備
// -----------------------------------------------------
$logger = null; // ロガーを初期化
$database = null; // Databaseインスタンスを初期化
$dugaApiClient = null; // DugaApiClientインスタンスを初期化
$dbBatchInserter = null; // DbBatchInsertインスタンスを初期化

try {
    // Loggerクラスのインスタンス化 (ログファイル名を指定)
    $logger = new Logger('duga_api_processing.log');
    $logger->log("Duga APIからのデータ取得とデータベース保存処理を開始します。");

    // データベース接続の確立
    // Databaseクラスに設定とLoggerを注入
    $database = new Database($dbConfig, $logger);
    $pdo = $database->getConnection(); // PDOインスタンスを取得

    // Duga APIクライアントのインスタンス化
    // DugaApiClientにAPI URL, APIキー, Loggerを注入
    $dugaApiClient = new DugaApiClient($dugaApiUrl, $dugaApiKey, $logger);

    // DbBatchInsertヘルパークラスのインスタンス化
    // DbBatchInsertにDatabaseとLoggerを注入
    $dbBatchInserter = new DbBatchInsert($database, $logger);

} catch (Exception $e) {
    // 初期設定段階でのエラーをログに記録し、スクリプトを終了
    error_log("CLI初期設定エラー: " . $e->getMessage()); // PHPのエラーログに出力
    if ($logger) { // ロガーが初期化されている場合のみ利用
        $logger->error("CLI初期設定中に致命的なエラーが発生しました: " . $e->getMessage()); // Dugaログに出力
    }
    die("初期設定中にエラーが発生しました。ログを確認してください。\n");
}

// -----------------------------------------------------
// コマンドライン引数のパース
// -----------------------------------------------------
$cli_options = getopt("", ["start_date::", "end_date::", "keyword::", "genre_id::", "agentid::", "bannerid::", "adult::", "sort::"]);

// コマンドライン引数がない場合はデフォルト値を適用
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
$current_page = 1; // 現在のページ番号 (ログと進捗表示用)
$total_processed_records = 0; // 全体の処理済みレコード数
$raw_data_buffer = []; // raw_api_data テーブルに挿入するためのバッファ
$products_buffer_temp = []; // products テーブルに挿入するためのデータと api_product_id の一時マッピング
$api_product_ids_in_batch = []; // 現在のバッチで処理するapi_product_idのリスト

try {
    while (true) {
        $current_offset = ($current_page - 1) * API_RECORDS_PER_REQUEST + 1;
        $logger->log("Duga APIからアイテムを取得中... (ページ: {$current_page}, offset: {$current_offset}, 件数: " . API_RECORDS_PER_REQUEST . ")");

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
        $api_data_batch = $dugaApiClient->getItems($current_offset, API_RECORDS_PER_REQUEST, $additional_api_params);

        // APIからのデータが空の場合、全てのデータ取得が完了したと判断しループを終了
        if (empty($api_data_batch)) {
            $logger->log("Duga APIから追加のアイテムデータが取得できませんでした。全てのAPIデータの取得が完了しました。");
            break; // ループを抜ける
        }

        // 取得したAPIデータをraw_api_dataとproductsの両方のバッファに準備
        foreach ($api_data_batch as $api_record) {
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
            $api_product_ids_in_batch[] = $content_id; // 製品IDを記録

            // products テーブル用の初期データ準備
            // ジャンルデータの堅牢な抽出
            $genre = null;
            if (isset($api_record['category']) && is_array($api_record['category']) && !empty($api_record['category'][0]['data']['name'])) {
                $genre = $api_record['category'][0]['data']['name'];
            }

            $product_entry_temp = [
                'product_id'    => $content_id,
                'title'         => $api_record['title'] ?? null,
                'release_date'  => $api_record['opendate'] ?? $api_record['releasedate'] ?? null,
                'maker_name'    => $api_record['makername'] ?? null,
                'genre'         => $genre,
                'url'           => $api_record['affiliateurl'] ?? $api_record['url'] ?? null,
                'image_url'     => $api_record['jacketimage'][0]['large'] ?? $api_record['posterimage'][0]['large'] ?? null,
                'source_api'    => API_SOURCE_NAME,
                'created_at'    => date('Y-m-d H:i:s'), // 新規作成時のみ、ON DUPLICATE KEY UPDATEでは更新しない
                'updated_at'    => date('Y-m-d H:i:s')
            ];
            $products_buffer_temp[$content_id] = $product_entry_temp;
        }

        // バッファが指定したチャンクサイズに達したら、データベースへのUPSERTを実行
        if (count($raw_data_buffer) >= DB_BUFFER_SIZE) {
            $logger->log("バッファが" . DB_BUFFER_SIZE . "件に達しました。データベースへのUPSERTを開始します。");
            
            $raw_data_upsert_columns = ['row_json_data', 'fetched_at', 'updated_at'];
            $products_upsert_columns = ['title', 'release_date', 'maker_name', 'genre', 'url', 'image_url', 'source_api', 'row_api_data_id', 'updated_at']; // created_at は更新しない

            try {
                // 1. raw_api_data テーブルへのUPSERT
                $dbBatchInserter->insertOrUpdate('raw_api_data', $raw_data_buffer, $raw_data_upsert_columns);
                $logger->log(count($raw_data_buffer) . "件の生データを 'raw_api_data' にUPSERTしました。");

                // 2. 挿入された raw_api_data のIDを取得し、products_buffer_tempに紐付ける
                // 各レコードごとにIDを取得する必要があるため、ループ内で取得
                $final_products_for_upsert = [];
                foreach ($products_buffer_temp as $api_id => $product_data) {
                    $raw_api_data_id = $dbBatchInserter->getRawApiDataId(API_SOURCE_NAME, $api_id);
                    if ($raw_api_data_id !== null) {
                        $product_data['row_api_data_id'] = $raw_api_data_id;
                        $final_products_for_upsert[] = $product_data;
                    } else {
                        $logger->error("警告: api_product_id '{$api_id}' の raw_api_data_id が見つかりませんでした。products テーブルにUPSERTされません。");
                    }
                }

                // 3. products テーブルへのUPSERT
                if (!empty($final_products_for_upsert)) {
                    $dbBatchInserter->insertOrUpdate('products', $final_products_for_upsert, $products_upsert_columns);
                    $total_processed_records += count($final_products_for_upsert);
                    $logger->log(count($final_products_for_upsert) . "件の商品データを 'products' にUPSERTしました。");
                } else {
                    $logger->log("products テーブルにUPSERTするデータがありませんでした。");
                }

                $logger->log("{$total_processed_records}件のデータをデータベースに正常に処理しました。次のバッチ処理に進みます。");

            } catch (Exception $e) {
                // DbBatchInsertのinsertOrUpdate内でトランザクションが管理されているため、
                // ここでは単純にエラーをログに記録し、例外を再スローします。
                $logger->error("データベースUPSERT中にエラーが発生しました: " . $e->getMessage());
                throw $e; // 上位のtry-catchブロックにエラーを再スロー
            }

            // バッファをクリア
            $raw_data_buffer = [];
            $products_buffer_temp = [];
            $api_product_ids_in_batch = [];
        }

        $current_page++;
        sleep(1); // APIへのリクエスト頻度を調整するため1秒待機
    }

    // ループ終了後、バッファに残っている未保存のデータを処理
    if (!empty($raw_data_buffer)) {
        $logger->log("処理終了。バッファに残っているデータをデータベースにUPSERTします。");
        
        $raw_data_upsert_columns = ['row_json_data', 'fetched_at', 'updated_at'];
        $products_upsert_columns = ['title', 'release_date', 'maker_name', 'genre', 'url', 'image_url', 'source_api', 'row_api_data_id', 'updated_at'];

        try {
            // 1. raw_api_data テーブルへのUPSERT (残り)
            $dbBatchInserter->insertOrUpdate('raw_api_data', $raw_data_buffer, $raw_data_upsert_columns);
            $logger->log(count($raw_data_buffer) . "件の残りの生データを 'raw_api_data' にUPSERTしました。");

            // 2. 挿入された raw_api_data のIDを取得し、products_buffer_tempに紐付ける (残り)
            $final_products_for_upsert = [];
            foreach ($products_buffer_temp as $api_id => $product_data) {
                $raw_api_data_id = $dbBatchInserter->getRawApiDataId(API_SOURCE_NAME, $api_id);
                if ($raw_api_data_id !== null) {
                    $product_data['row_api_data_id'] = $raw_api_data_id;
                    $final_products_for_upsert[] = $product_data;
                } else {
                    $logger->error("警告: api_product_id '{$api_id}' の raw_api_data_id が見つかりませんでした。products テーブルにUPSERTされません。(最終バッチ)");
                }
            }

            // 3. products テーブルへのUPSERT (残り)
            if (!empty($final_products_for_upsert)) {
                $dbBatchInserter->insertOrUpdate('products', $final_products_for_upsert, $products_upsert_columns);
                $total_processed_records += count($final_products_for_upsert);
                $logger->log(count($final_products_for_upsert) . "件の残りの商品データを 'products' にUPSERTしました。");
            } else {
                $logger->log("products テーブルにUPSERTする残りのデータがありませんでした。");
            }
            
            $logger->log("残りのデータも正常にUPSERTされました。");

        } catch (Exception $e) {
            $logger->error("データベースUPSERT中にエラーが発生しました。(最終バッチ): " . $e->getMessage());
            throw $e; // 上位のtry-catchブロックにエラーを再スロー
        }
    }

    $logger->log("Duga APIからの全データ取得とデータベース保存処理が完了しました。");
    $logger->log("合計処理済みレコード数: {$total_processed_records}件");

} catch (Exception $e) {
    $logger->error("Duga API処理中に致命的なエラーが発生しました: " . $e->getMessage());
    $logger->error("エラー発生箇所: ファイル " . $e->getFile() . " 行 " . $e->getLine());
} finally {
    // スクリプト終了時にPDO接続は自動的に閉じられますが、明示的にnullを設定することも可能です
    $database = null; // Databaseインスタンスをnullに設定することで、PDO接続もクローズされます
    $logger->log("スクリプトを終了します。");
}

exit(0);
