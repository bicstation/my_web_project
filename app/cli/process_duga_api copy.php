<?php
// C:\project\my_web_project\app\cli\process_duga_api.php


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// これが出力されるかどうかが非常に重要です。
// もしこれすら出力されないなら、PHPの実行環境自体に問題があります。
error_log("--- スクリプト実行開始（強制エラー表示ON） ---", 4); // 4 はSAPIログ（CLIならstderr）に出力
// または直接コンソールに出力
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
// use PDOException; // PDOExceptionはグローバル名前空間にあるが、明示的にuseしても問題ない

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
const DB_BUFFER_SIZE = 500;           // データベースへのバッチ処理のチャンクサイズ
const API_SOURCE_NAME = 'duga';       // このAPIのソース名

// .env から Duga API の設定を取得
$dugaApiUrl = $_ENV['DUGA_API_URL'] ?? 'http://affapi.duga.jp/search';
$dugaApiKey = $_ENV['DUGA_API_KEY'] ?? 'YOUR_DUGA_API_KEY_HERE';

// .env からデータベース設定を取得
$dbConfig = [
    'host'      => $_ENV['DB_HOST'] ?? 'localhost',
    'dbname'    => $_ENV['DB_NAME'] ?? 'tiper', // DB名を 'tiper' に修正
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

try {
    $logger = new Logger('duga_api_processing.log');
    $logger->log("Duga APIからのデータ取得とデータベース保存処理を開始します。");

    $database = new Database($dbConfig, $logger);
    $pdo = $database->getConnection(); // PDOインスタンスを取得

    $dugaApiClient = new DugaApiClient($dugaApiUrl, $dugaApiKey, $logger);

    $dbBatchInserter = new DbBatchInsert($database, $logger);

} catch (Exception $e) {
    error_log("CLI初期設定エラー: " . $e->getMessage());
    if ($logger) {
        $logger->error("CLI初期設定中に致命的なエラーが発生しました: " . $e->getMessage());
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
$genre_id   = $cli_options['genre_id'] ?? null; // Duga APIには genre_id パラメータがない可能性あり。category_idなどに置き換えるか確認が必要。
$agentid    = $cli_options['agentid'] ?? DEFAULT_AGENT_ID;
$bannerid   = $cli_options['bannerid'] ?? DEFAULT_BANNER_ID;
$adult      = $cli_options['adult'] ?? DEFAULT_ADULT_PARAM;
$sort       = $cli_options['sort'] ?? DEFAULT_SORT_PARAM;

$logger->log("CLI Arguments processed: " . json_encode([
    'start_date' => $start_date,
    'end_date' => $end_date,
    'keyword' => $keyword,
    'genre_id' => $genre_id, // Duga APIのパラメータを正確に確認すること
    'agentid' => $agentid,
    'bannerid' => $bannerid,
    'adult' => $adult,
    'sort' => $sort
]));


// -----------------------------------------------------
// 補助関数: 分類データの処理 (重複登録防止とID取得)
// -----------------------------------------------------
/**
 * 分類データをデータベースにUPSERTし、そのIDを返す。
 *
 * @param DbBatchInsert $dbBatchInserter DbBatchInsertインスタンス
 * @param Logger $logger Loggerインスタンス
 * @param string $tableName テーブル名 (genres, labels, directors, series, actors, categories)
 * @param array $data UPSERTするデータ (name, slug, duga_XXXX_idなど)
 * @return int|null 挿入または更新されたレコードのID、またはnull（失敗した場合）
 */
function processClassificationData(DbBatchInsert $dbBatchInserter, Logger $logger, string $tableName, array $data): ?int
{
    // 重複チェック用のキー (duga_XXXX_id または name)
    $uniqueKeyColumn = '';
    $uniqueKeyValue = '';

    if (isset($data['duga_' . rtrim($tableName, 's') . '_id'])) { // 例: 'duga_genre_id'
        $uniqueKeyColumn = 'duga_' . rtrim($tableName, 's') . '_id';
        $uniqueKeyValue = $data[$uniqueKeyColumn];
    } elseif (isset($data['name'])) {
        $uniqueKeyColumn = 'name';
        $uniqueKeyValue = $data[$uniqueKeyColumn];
    } else {
        $logger->error("警告: テーブル '{$tableName}' のUPSERTに必要なユニークキー (duga_idまたはname) が見つかりません。");
        return null;
    }

    // まず既存レコードがあるか確認
    $existingId = null;
    try {
        $stmt = $dbBatchInserter->getDatabase()->getConnection()->prepare(
            "SELECT id FROM `{$tableName}` WHERE `{$uniqueKeyColumn}` = :value"
        );
        $stmt->bindParam(':value', $uniqueKeyValue);
        $stmt->execute();
        $existingId = $stmt->fetchColumn();
    } catch (PDOException $e) {
        $logger->error("分類データ検索エラー ({$tableName}, {$uniqueKeyColumn}='{$uniqueKeyValue}'): " . $e->getMessage());
        return null;
    }

    $idToReturn = null;
    if ($existingId) {
        // 既存レコードがあれば更新 (必要に応じて)
        try {
            $updateColumns = array_keys($data);
            // created_at は更新しない
            $updateColumns = array_diff($updateColumns, ['id', 'created_at']);
            
            $updateSet = [];
            foreach ($updateColumns as $col) {
                if ($col === $uniqueKeyColumn) continue; // ユニークキー自体は更新しない（ON DUPLICATE KEY UPDATEを使わない場合）
                $updateSet[] = "`{$col}` = :{$col}";
            }
            if (empty($updateSet)) { // 更新するカラムがなければ何もしない
            return $existingId;
            }

            $stmt = $dbBatchInserter->getDatabase()->getConnection()->prepare(
                "UPDATE `{$tableName}` SET " . implode(', ', $updateSet) . ", `updated_at` = CURRENT_TIMESTAMP WHERE `id` = :id"
            );
            $data['id'] = $existingId; // IDで更新するため
            foreach ($data as $key => $value) {
                if ($key === $uniqueKeyColumn || $key === 'created_at') continue;
                $stmt->bindValue(':' . $key, $value);
            }
            $stmt->bindParam(':id', $existingId);
            $stmt->execute();
            $idToReturn = $existingId;
        } catch (PDOException $e) {
            $logger->error("分類データ更新エラー ({$tableName}, ID={$existingId}): " . $e->getMessage());
            return null;
        }
    } else {
        // 新規レコードを挿入
        try {
            $insertColumns = array_keys($data);
            $placeholders = array_map(fn($col) => ':' . $col, $insertColumns);

            $stmt = $dbBatchInserter->getDatabase()->getConnection()->prepare(
                "INSERT INTO `{$tableName}` (`" . implode('`,`', $insertColumns) . "`) VALUES (" . implode(',', $placeholders) . ")"
            );
            foreach ($data as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            $stmt->execute();
            $idToReturn = $dbBatchInserter->getDatabase()->getConnection()->lastInsertId();
        } catch (PDOException $e) {
            // エラーコード23000はユニークキー制約違反（重複挿入）を示す
            if ($e->getCode() === '23000') { // Duplicate entry error
                $logger->warning("警告: '{$tableName}' に重複データ検出 (キー: {$uniqueKeyColumn}, 値: {$uniqueKeyValue})。再取得を試みます。");
                // 競合により挿入できなかった場合、再度IDを取得して返す
                try {
                    $stmt = $dbBatchInserter->getDatabase()->getConnection()->prepare(
                        "SELECT id FROM `{$tableName}` WHERE `{$uniqueKeyColumn}` = :value"
                    );
                    $stmt->bindParam(':value', $uniqueKeyValue);
                    $stmt->execute();
                    $idToReturn = $stmt->fetchColumn();
                } catch (PDOException $e_inner) {
                    $logger->error("重複データ後のID再取得エラー ({$tableName}, {$uniqueKeyColumn}='{$uniqueKeyValue}'): " . $e_inner->getMessage());
                    return null;
                }
            } else {
                $logger->error("分類データ挿入エラー ({$tableName}, {$uniqueKeyColumn}='{$uniqueKeyValue}'): " . $e->getMessage());
                return null;
            }
        }
    }
    return $idToReturn;
}


// -----------------------------------------------------
// データ取得と保存のメインロジック
// -----------------------------------------------------
$current_offset = 1; // Duga APIの offset は1から始まる
$total_processed_records = 0; // 全体の処理済みレコード数
$raw_data_buffer = []; // raw_api_data テーブルに挿入するためのバッファ
$products_buffer_temp = []; // products テーブルに挿入するためのデータと api_product_id の一時マッピング

// 分類データ用の一時バッファ
$categories_buffer = []; // key: category_name or duga_category_id, value: ['name' => ..., 'slug' => ...]
$genres_buffer = [];
$labels_buffer = [];
$directors_buffer = [];
$series_buffer = [];
$actors_buffer = [];

// 中間テーブル用の一時バッファ
$product_categories_buffer = []; // ['product_id' => ..., 'category_id' => ...]
$product_genres_buffer = [];
$product_labels_buffer = [];
$product_directors_buffer = [];
$product_series_buffer = [];
$product_actors_buffer = [];


// APIから取得すべき総件数 (初回APIコールで設定される)
$total_api_results = PHP_INT_MAX; // 初期値はPHPの最大整数で無限ループを回避

try {
    while ($total_processed_records < $total_api_results) {
        $logger->log("Duga APIからアイテムを取得中... (offset: {$current_offset}, 件数: " . API_RECORDS_PER_REQUEST . ")");

        $additional_api_params = [];
        if ($start_date) $additional_api_params['release_date_from'] = $start_date;
        if ($end_date)   $additional_api_params['release_date_to'] = $end_date;
        if ($keyword)    $additional_api_params['keyword'] = $keyword;
        if ($genre_id)   $additional_api_params['genre_id'] = $genre_id; // Duga APIのパラメータ名を確認
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

        // 初回リクエスト時に全体のヒット数を設定
        if ($current_offset === 1) {
            $total_api_results = $current_total_hits;
            $logger->log("Duga APIから報告された検索結果総数: {$total_api_results}件");
            if ($total_api_results === 0) {
                $logger->log("検索結果が0件のため、処理を終了します。");
                break; // 0件ならループを抜ける
            }
        }

        if (empty($api_data_batch)) {
            $logger->log("Duga APIから追加のアイテムデータが取得できませんでした。全てのAPIデータの取得が完了しました。");
            break; // ループを抜ける
        }

        // 取得したAPIデータをraw_api_dataとproducts、および関連テーブルのバッファに準備
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

            // products テーブル用のデータ準備
            $release_date = null;
            if (isset($api_record['opendate'])) {
                $release_date = str_replace('/', '-', $api_record['opendate']); // YYYY/MM/DD -> YYYY-MM-DD
            } elseif (isset($api_record['releasedate'])) {
                $release_date = str_replace('/', '-', $api_record['releasedate']);
            }

            $price = null;
            if (isset($api_record['price'])) {
                // "1,480円" -> 1480.00
                $price_str = str_replace(['¥', '円', ','], '', $api_record['price']);
                if (strpos($price_str, '~') !== false) { // "400円～" のようなケース
                    $price_str = explode('~', $price_str)[0];
                }
                $price = is_numeric($price_str) ? (float)$price_str : null;
            }

            // 画像URLの抽出ヘルパー関数 (配列の最初の要素の指定キーを取得)
            $extractImageUrl = function($imageArray, $key) {
                return isset($imageArray[0][$key]) ? $imageArray[0][$key] : null;
            };

            $product_entry_temp = [
                'product_id'          => $content_id,
                'title'               => $api_record['title'] ?? null,
                'original_title'      => $api_record['originaltitle'] ?? null,
                'caption'             => $api_record['caption'] ?? null,
                'release_date'        => $release_date,
                'maker_name'          => $api_record['makername'] ?? null,
                'itemno'              => $api_record['itemno'] ?? null,
                'price'               => $price,
                'volume'              => $api_record['volume'] ?? null,
                'url'                 => $api_record['url'] ?? null,
                'affiliate_url'       => $api_record['affiliateurl'] ?? null,
                'image_url_small'     => $extractImageUrl($api_record['posterimage'] ?? [], 'small'),
                'image_url_medium'    => $extractImageUrl($api_record['posterimage'] ?? [], 'midium'),
                'image_url_large'     => $extractImageUrl($api_record['posterimage'] ?? [], 'large'),
                'jacket_url_small'    => $extractImageUrl($api_record['jacketimage'] ?? [], 'small'),
                'jacket_url_medium'   => $extractImageUrl($api_record['jacketimage'] ?? [], 'midium'),
                'jacket_url_large'    => $extractImageUrl($api_record['jacketimage'] ?? [], 'large'),
                'sample_movie_url'    => $extractImageUrl($api_record['samplemovie'] ?? [], 'midium')['movie'] ?? null, // samplemovieはさらにネスト
                'sample_movie_capture_url' => $extractImageUrl($api_record['samplemovie'] ?? [], 'midium')['capture'] ?? null,
                'source_api'          => API_SOURCE_NAME,
                'created_at'          => date('Y-m-d H:i:s'),
                'updated_at'          => date('Y-m-d H:i:s')
            ];
            $products_buffer_temp[$content_id] = $product_entry_temp;

            // 分類データと中間テーブルのデータ準備
            // categories
            if (isset($api_record['category']) && is_array($api_record['category'])) {
                foreach ($api_record['category'] as $category_data_wrapper) {
                    $category_detail = $category_data_wrapper['data'] ?? null;
                    if ($category_detail && isset($category_detail['id']) && isset($category_detail['name'])) {
                        $category_name = trim($category_detail['name']);
                        $category_duga_id = trim($category_detail['id']);
                        if (!empty($category_name) && !empty($category_duga_id)) {
                            // スラグの生成 (URLフレンドリーな文字列)
                            $category_slug = str_replace(' ', '-', strtolower($category_name)); // 例: "M男" -> "m-otoko" など、より堅牢なスラグ生成ロジックが必要であれば追加
                            $categories_buffer[$category_duga_id] = [ // ユニークキーをduga_category_idにする
                                'name' => $category_name,
                                'slug' => $category_slug,
                                'duga_category_id' => $category_duga_id,
                                'level' => 0 // Duga APIのカテゴリは通常大分類と仮定
                            ];
                            $product_categories_buffer[] = [
                                'product_id' => $content_id,
                                'category_duga_id' => $category_duga_id // 後で解決するための仮のキー
                            ];
                        }
                    }
                }
            }
            
            // genres (Duga APIでは'category'の下にあるが、意味的にジャンルとして扱う)
            // このロジックは上記categoryと重複する可能性があるため、APIレスポンスの正確な構造に基づいて調整してください。
            // Duga APIでは通常 'category' がカテゴリとジャンルを兼ねる傾向があります。
            // もし明確な 'genre' フィールドがある場合は、ここにロジックを追加します。
            // 現状、raw_api_dataの例から判断すると、`category`がジャンル的な役割も兼ねている可能性が高いです。
            // ここでは、`category`を`categories`と`genres`の両方に紐付けると仮定します。
            if (isset($api_record['category']) && is_array($api_record['category'])) {
                foreach ($api_record['category'] as $category_data_wrapper) {
                    $category_detail = $category_data_wrapper['data'] ?? null;
                    if ($category_detail && isset($category_detail['id']) && isset($category_detail['name'])) {
                        $genre_name = trim($category_detail['name']);
                        $genre_duga_id = trim($category_detail['id']);
                        if (!empty($genre_name) && !empty($genre_duga_id)) {
                            $genre_slug = str_replace(' ', '-', strtolower($genre_name));
                            $genres_buffer[$genre_duga_id] = [
                                'name' => $genre_name,
                                'slug' => $genre_slug,
                                'duga_genre_id' => $genre_duga_id
                            ];
                            $product_genres_buffer[] = [
                                'product_id' => $content_id,
                                'genre_duga_id' => $genre_duga_id
                            ];
                        }
                    }
                }
            }


            // labels
            if (isset($api_record['label']) && is_array($api_record['label'])) {
                foreach ($api_record['label'] as $label_detail) {
                    if (isset($label_detail['id']) && isset($label_detail['name'])) {
                        $label_name = trim($label_detail['name']);
                        $label_duga_id = trim($label_detail['id']);
                        if (!empty($label_name) && !empty($label_duga_id)) {
                            $label_slug = str_replace(' ', '-', strtolower($label_name));
                            $labels_buffer[$label_duga_id] = [
                                'name' => $label_name,
                                'slug' => $label_slug,
                                'duga_label_id' => $label_duga_id
                            ];
                            $product_labels_buffer[] = [
                                'product_id' => $content_id,
                                'label_duga_id' => $label_duga_id
                            ];
                        }
                    }
                }
            }

            // directors
            if (isset($api_record['director']) && is_array($api_record['director'])) {
                foreach ($api_record['director'] as $director_data_wrapper) {
                    $director_detail = $director_data_wrapper['data'] ?? null;
                    if ($director_detail && isset($director_detail['id']) && isset($director_detail['name'])) {
                        $director_name = trim($director_detail['name']);
                        $director_duga_id = trim($director_detail['id']);
                        if (!empty($director_name) && !empty($director_duga_id)) {
                            $director_slug = str_replace(' ', '-', strtolower($director_name));
                            $directors_buffer[$director_duga_id] = [
                                'name' => $director_name,
                                'slug' => $director_slug,
                                'duga_director_id' => $director_duga_id
                            ];
                            $product_directors_buffer[] = [
                                'product_id' => $content_id,
                                'director_duga_id' => $director_duga_id
                            ];
                        }
                    }
                }
            }

            // series
            if (isset($api_record['series']) && is_array($api_record['series'])) {
                foreach ($api_record['series'] as $series_detail) {
                    if (isset($series_detail['id']) && isset($series_detail['name'])) {
                        $series_name = trim($series_detail['name']);
                        $series_duga_id = trim($series_detail['id']);
                        if (!empty($series_name) && !empty($series_duga_id)) {
                            $series_slug = str_replace(' ', '-', strtolower($series_name));
                            $series_buffer[$series_duga_id] = [
                                'name' => $series_name,
                                'slug' => $series_slug,
                                'duga_series_id' => $series_duga_id
                            ];
                            $product_series_buffer[] = [
                                'product_id' => $content_id,
                                'series_duga_id' => $series_duga_id
                            ];
                        }
                    }
                }
            }

            // actors (Duga APIでは'performer'として提供されることが多い)
            if (isset($api_record['performer']) && is_array($api_record['performer'])) {
                foreach ($api_record['performer'] as $performer_data_wrapper) {
                    $performer_detail = $performer_data_wrapper['data'] ?? null;
                    if ($performer_detail && isset($performer_detail['id']) && isset($performer_detail['name'])) {
                        $actor_name = trim($performer_detail['name']);
                        $actor_duga_id = trim($performer_detail['id']);
                        $actor_kana = trim($performer_detail['kana'] ?? '');
                        if (!empty($actor_name) && !empty($actor_duga_id)) {
                            $actor_slug = str_replace(' ', '-', strtolower($actor_name));
                            $actors_buffer[$actor_duga_id] = [
                                'name' => $actor_name,
                                'slug' => $actor_slug,
                                'duga_actor_id' => $actor_duga_id,
                                'kana' => $actor_kana
                            ];
                            $product_actors_buffer[] = [
                                'product_id' => $content_id,
                                'actor_duga_id' => $actor_duga_id
                            ];
                        }
                    }
                }
            }
        } // foreach ($api_data_batch as $api_record_wrapper) 終了

        // バッファが指定したチャンクサイズに達したら、データベースへのUPSERTを実行
        if (count($raw_data_buffer) >= DB_BUFFER_SIZE) {
            $logger->log("バッファが" . DB_BUFFER_SIZE . "件に達しました。データベースへのUPSERTを開始します。");
            
            // トランザクション開始
            $pdo->beginTransaction();
            try {
                // 1. raw_api_data テーブルへのUPSERT
                $raw_data_upsert_columns = ['row_json_data', 'fetched_at', 'updated_at'];
                $dbBatchInserter->insertOrUpdate('raw_api_data', $raw_data_buffer, $raw_data_upsert_columns);
                $logger->log(count($raw_data_buffer) . "件の生データを 'raw_api_data' にUPSERTしました。");

                // 2. products_buffer_temp に raw_api_data_id を紐付け、products テーブルへのUPSERTを準備
                $final_products_for_upsert = [];
                foreach ($products_buffer_temp as $api_id => $product_data) {
                    $raw_api_data_id = $dbBatchInserter->getRawApiDataId(API_SOURCE_NAME, $api_id);
                    if ($raw_api_data_id !== null) {
                        $product_data['raw_api_data_id'] = $raw_api_data_id;
                        $final_products_for_upsert[] = $product_data;
                    } else {
                        $logger->error("警告: api_product_id '{$api_id}' の raw_api_data_id が見つかりませんでした。products テーブルにUPSERTされません。");
                    }
                }

                // products_upsert_columns を新しいスキーマに合わせて更新
                $products_upsert_columns = [
                    'title', 'original_title', 'caption', 'release_date', 'maker_name', 'itemno', 'price', 'volume',
                    'url', 'affiliate_url', 'image_url_small', 'image_url_medium', 'image_url_large',
                    'jacket_url_small', 'jacket_url_medium', 'jacket_url_large',
                    'sample_movie_url', 'sample_movie_capture_url', 'source_api', 'raw_api_data_id', 'updated_at'
                ];
                
                // 3. products テーブルへのUPSERT
                if (!empty($final_products_for_upsert)) {
                    $dbBatchInserter->insertOrUpdate('products', $final_products_for_upsert, $products_upsert_columns);
                    $logger->log(count($final_products_for_upsert) . "件の商品データを 'products' にUPSERTしました。");
                } else {
                    $logger->log("products テーブルにUPSERTするデータがありませんでした。");
                }

                // 4. 分類テーブル (categories, genres, labels, directors, series, actors) の処理
                // product_id は products の product_id を使用し、_duga_id で検索してDBのIDを取得し、紐付けます
                $category_map = []; // duga_category_id => db_category_id
                foreach ($categories_buffer as $duga_id => $data) {
                    $db_id = processClassificationData($dbBatchInserter, $logger, 'categories', $data);
                    if ($db_id !== null) {
                        $category_map[$duga_id] = $db_id;
                    }
                }
                
                $genre_map = []; // duga_genre_id => db_genre_id
                foreach ($genres_buffer as $duga_id => $data) {
                    $db_id = processClassificationData($dbBatchInserter, $logger, 'genres', $data);
                    if ($db_id !== null) {
                        $genre_map[$duga_id] = $db_id;
                    }
                }

                $label_map = [];
                foreach ($labels_buffer as $duga_id => $data) {
                    $db_id = processClassificationData($dbBatchInserter, $logger, 'labels', $data);
                    if ($db_id !== null) {
                        $label_map[$duga_id] = $db_id;
                    }
                }

                $director_map = [];
                foreach ($directors_buffer as $duga_id => $data) {
                    $db_id = processClassificationData($dbBatchInserter, $logger, 'directors', $data);
                    if ($db_id !== null) {
                        $director_map[$duga_id] = $db_id;
                    }
                }

                $series_map = [];
                foreach ($series_buffer as $duga_id => $data) {
                    $db_id = processClassificationData($dbBatchInserter, $logger, 'series', $data);
                    if ($db_id !== null) {
                        $series_map[$duga_id] = $db_id;
                    }
                }

                $actor_map = [];
                foreach ($actors_buffer as $duga_id => $data) {
                    $db_id = processClassificationData($dbBatchInserter, $logger, 'actors', $data);
                    if ($db_id !== null) {
                        $actor_map[$duga_id] = $db_id;
                    }
                }

                // 5. 中間テーブルへのUPSERT
                $final_product_categories = [];
                foreach ($product_categories_buffer as $entry) {
                    if (isset($category_map[$entry['category_duga_id']])) {
                        $final_product_categories[] = [
                            'product_id' => $entry['product_id'],
                            'category_id' => $category_map[$entry['category_duga_id']]
                        ];
                    }
                }
                if (!empty($final_product_categories)) {
                    $dbBatchInserter->insertOrUpdate('product_categories', $final_product_categories, [], ['product_id', 'category_id']);
                }

                $final_product_genres = [];
                foreach ($product_genres_buffer as $entry) {
                    if (isset($genre_map[$entry['genre_duga_id']])) {
                        $final_product_genres[] = [
                            'product_id' => $entry['product_id'],
                            'genre_id' => $genre_map[$entry['genre_duga_id']]
                        ];
                    }
                }
                if (!empty($final_product_genres)) {
                    $dbBatchInserter->insertOrUpdate('product_genres', $final_product_genres, [], ['product_id', 'genre_id']);
                }
                
                $final_product_labels = [];
                foreach ($product_labels_buffer as $entry) {
                    if (isset($label_map[$entry['label_duga_id']])) {
                        $final_product_labels[] = [
                            'product_id' => $entry['product_id'],
                            'label_id' => $label_map[$entry['label_duga_id']]
                        ];
                    }
                }
                if (!empty($final_product_labels)) {
                    $dbBatchInserter->insertOrUpdate('product_labels', $final_product_labels, [], ['product_id', 'label_id']);
                }

                $final_product_directors = [];
                foreach ($product_directors_buffer as $entry) {
                    if (isset($director_map[$entry['director_duga_id']])) {
                        $final_product_directors[] = [
                            'product_id' => $entry['product_id'],
                            'director_id' => $director_map[$entry['director_duga_id']]
                        ];
                    }
                }
                if (!empty($final_product_directors)) {
                    $dbBatchInserter->insertOrUpdate('product_directors', $final_product_directors, [], ['product_id', 'director_id']);
                }

                $final_product_series = [];
                foreach ($product_series_buffer as $entry) {
                    if (isset($series_map[$entry['series_duga_id']])) {
                        $final_product_series[] = [
                            'product_id' => $entry['product_id'],
                            'series_id' => $series_map[$entry['series_duga_id']]
                        ];
                    }
                }
                if (!empty($final_product_series)) {
                    $dbBatchInserter->insertOrUpdate('product_series', $final_product_series, [], ['product_id', 'series_id']);
                }

                $final_product_actors = [];
                foreach ($product_actors_buffer as $entry) {
                    if (isset($actor_map[$entry['actor_duga_id']])) {
                        $final_product_actors[] = [
                            'product_id' => $entry['product_id'],
                            'actor_id' => $actor_map[$entry['actor_duga_id']]
                        ];
                    }
                }
                if (!empty($final_product_actors)) {
                    $dbBatchInserter->insertOrUpdate('product_actors', $final_product_actors, [], ['product_id', 'actor_id']);
                }

                $pdo->commit();
                $total_processed_records += count($final_products_for_upsert); // productsにUPSERTされた数だけカウント
                $logger->log("{$total_processed_records}件のデータをデータベースに正常に処理しました。次のバッチ処理に進みます。");

            } catch (Exception $e) {
                $pdo->rollBack();
                $logger->error("データベースUPSERT中にエラーが発生しました: " . $e->getMessage());
                throw $e; // 上位のtry-catchブロックで捕捉
            }

            // バッファをクリア
            $raw_data_buffer = [];
            $products_buffer_temp = [];
            $categories_buffer = [];
            $genres_buffer = [];
            $labels_buffer = [];
            $directors_buffer = [];
            $series_buffer = [];
            $actors_buffer = [];
            $product_categories_buffer = [];
            $product_genres_buffer = [];
            $product_labels_buffer = [];
            $product_directors_buffer = [];
            $product_series_buffer = [];
            $product_actors_buffer = [];
        }

        $current_offset += API_RECORDS_PER_REQUEST; // 次のオフセットへ
        sleep(1); // APIへのリクエスト頻度を調整するため1秒待機 (API規約に従う)
    }

    // ループ終了後、バッファに残っている未保存のデータを処理
    if (!empty($raw_data_buffer)) {
        $logger->log("処理終了。バッファに残っているデータをデータベースにUPSERTします。");
        
        $pdo->beginTransaction();
        try {
            // 1. raw_api_data テーブルへのUPSERT (残り)
            $raw_data_upsert_columns = ['row_json_data', 'fetched_at', 'updated_at'];
            $dbBatchInserter->insertOrUpdate('raw_api_data', $raw_data_buffer, $raw_data_upsert_columns);
            $logger->log(count($raw_data_buffer) . "件の残りの生データを 'raw_api_data' にUPSERTしました。");

            // 2. products_buffer_temp に raw_api_data_id を紐付け (残り)
            $final_products_for_upsert = [];
            foreach ($products_buffer_temp as $api_id => $product_data) {
                $raw_api_data_id = $dbBatchInserter->getRawApiDataId(API_SOURCE_NAME, $api_id);
                if ($raw_api_data_id !== null) {
                    $product_data['raw_api_data_id'] = $raw_api_data_id;
                    $final_products_for_upsert[] = $product_data;
                } else {
                    $logger->error("警告: api_product_id '{$api_id}' の raw_api_data_id が見つかりませんでした。products テーブルにUPSERTされません。(最終バッチ)");
                }
            }

            // products_upsert_columns (最終バッチ用も同じ)
            $products_upsert_columns = [
                'title', 'original_title', 'caption', 'release_date', 'maker_name', 'itemno', 'price', 'volume',
                'url', 'affiliate_url', 'image_url_small', 'image_url_medium', 'image_url_large',
                'jacket_url_small', 'jacket_url_medium', 'jacket_url_large',
                'sample_movie_url', 'sample_movie_capture_url', 'source_api', 'raw_api_data_id', 'updated_at'
            ];

            // 3. products テーブルへのUPSERT (残り)
            if (!empty($final_products_for_upsert)) {
                $dbBatchInserter->insertOrUpdate('products', $final_products_for_upsert, $products_upsert_columns);
                $logger->log(count($final_products_for_upsert) . "件の残りの商品データを 'products' にUPSERTしました。");
            } else {
                $logger->log("products テーブルにUPSERTする残りのデータがありませんでした。");
            }
            
            // 4. 残りの分類テーブルの処理 (categories, genres, labels, directors, series, actors)
            $category_map = [];
            foreach ($categories_buffer as $duga_id => $data) {
                $db_id = processClassificationData($dbBatchInserter, $logger, 'categories', $data);
                if ($db_id !== null) {
                    $category_map[$duga_id] = $db_id;
                }
            }
            $genre_map = [];
            foreach ($genres_buffer as $duga_id => $data) {
                $db_id = processClassificationData($dbBatchInserter, $logger, 'genres', $data);
                if ($db_id !== null) {
                    $genre_map[$duga_id] = $db_id;
                }
            }
            $label_map = [];
            foreach ($labels_buffer as $duga_id => $data) {
                $db_id = processClassificationData($dbBatchInserter, $logger, 'labels', $data);
                if ($db_id !== null) {
                    $label_map[$duga_id] = $db_id;
                }
            }
            $director_map = [];
            foreach ($directors_buffer as $duga_id => $data) {
                $db_id = processClassificationData($dbBatchInserter, $logger, 'directors', $data);
                if ($db_id !== null) {
                    $director_map[$duga_id] = $db_id;
                }
            }
            $series_map = [];
            foreach ($series_buffer as $duga_id => $data) {
                $db_id = processClassificationData($dbBatchInserter, $logger, 'series', $data);
                if ($db_id !== null) {
                    $series_map[$duga_id] = $db_id;
                }
            }
            $actor_map = [];
            foreach ($actors_buffer as $duga_id => $data) {
                $db_id = processClassificationData($dbBatchInserter, $logger, 'actors', $data);
                if ($db_id !== null) {
                    $actor_map[$duga_id] = $db_id;
                }
            }

            // 5. 残りの中間テーブルへのUPSERT
            $final_product_categories = [];
            foreach ($product_categories_buffer as $entry) {
                if (isset($category_map[$entry['category_duga_id']])) {
                    $final_product_categories[] = [
                        'product_id' => $entry['product_id'],
                        'category_id' => $category_map[$entry['category_duga_id']]
                    ];
                }
            }
            if (!empty($final_product_categories)) {
                $dbBatchInserter->insertOrUpdate('product_categories', $final_product_categories, [], ['product_id', 'category_id']);
            }

            $final_product_genres = [];
            foreach ($product_genres_buffer as $entry) {
                if (isset($genre_map[$entry['genre_duga_id']])) {
                    $final_product_genres[] = [
                        'product_id' => $entry['product_id'],
                        'genre_id' => $genre_map[$entry['genre_duga_id']]
                    ];
                }
            }
            if (!empty($final_product_genres)) {
                $dbBatchInserter->insertOrUpdate('product_genres', $final_product_genres, [], ['product_id', 'genre_id']);
            }
            
            $final_product_labels = [];
            foreach ($product_labels_buffer as $entry) {
                if (isset($label_map[$entry['label_duga_id']])) {
                    $final_product_labels[] = [
                        'product_id' => $entry['product_id'],
                        'label_id' => $label_map[$entry['label_duga_id']]
                    ];
                }
            }
            if (!empty($final_product_labels)) {
                $dbBatchInserter->insertOrUpdate('product_labels', $final_product_labels, [], ['product_id', 'label_id']);
            }

            $final_product_directors = [];
            foreach ($product_directors_buffer as $entry) {
                if (isset($director_map[$entry['director_duga_id']])) {
                    $final_product_directors[] = [
                        'product_id' => $entry['product_id'],
                        'director_id' => $director_map[$entry['director_duga_id']]
                    ];
                }
            }
            if (!empty($final_product_directors)) {
                $dbBatchInserter->insertOrUpdate('product_directors', $final_product_directors, [], ['product_id', 'director_id']);
            }

            $final_product_series = [];
            foreach ($product_series_buffer as $entry) {
                if (isset($series_map[$entry['series_duga_id']])) {
                    $final_product_series[] = [
                        'product_id' => $entry['product_id'],
                        'series_id' => $series_map[$entry['series_duga_id']]
                    ];
                }
            }
            if (!empty($final_product_series)) {
                $dbBatchInserter->insertOrUpdate('product_series', $final_product_series, [], ['product_id', 'series_id']);
            }

            $final_product_actors = [];
            foreach ($product_actors_buffer as $entry) {
                if (isset($actor_map[$entry['actor_duga_id']])) {
                    $final_product_actors[] = [
                        'product_id' => $entry['product_id'],
                        'actor_id' => $actor_map[$entry['actor_duga_id']]
                    ];
                }
            }
            if (!empty($final_product_actors)) {
                $dbBatchInserter->insertOrUpdate('product_actors', $final_product_actors, [], ['product_id', 'actor_id']);
            }

            $pdo->commit();
            $total_processed_records += count($final_products_for_upsert); // productsにUPSERTされた数だけカウント
            $logger->log("残りのデータも正常にUPSERTされました。");

        } catch (Exception $e) {
            $pdo->rollBack();
            $logger->error("データベースUPSERT中にエラーが発生しました。(最終バッチ): " . $e->getMessage());
            throw $e;
        }
    }

    $logger->log("Duga APIからの全データ取得とデータベース保存処理が完了しました。");
    $logger->log("合計処理済みレコード数: {$total_processed_records}件");

} catch (Exception $e) {
    $logger->error("Duga API処理中に致命的なエラーが発生しました: " . $e->getMessage());
    $logger->error("エラー発生箇所: ファイル " . $e->getFile() . " 行 " . $e->getLine());
} finally {
    $database = null;
    $logger->log("スクリプトを終了します。");
}

exit(0);