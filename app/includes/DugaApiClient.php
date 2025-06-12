<?php
// C:\project\my_web_project\app\cli\process_duga_api.php

// 共通のインクルードファイルを読み込む
require_once __DIR__ . '/../includes/db_config.php';       // データベース接続関数 connectDB() を提供
require_once __DIR__ . '/../includes/Logger.php';          // ログ出力用の Logger クラスを提供
require_once __DIR__ . '/../includes/DbBatchInsert.php';   // バルクインサート用の DbBatchInsert クラスを提供
require_once __DIR__ . '/../includes/DugaApiClient.php';   // Duga APIへのリクエストを処理する DugaApiClient クラスを提供

// このスクリプトがCLI (コマンドラインインターフェース) から実行されたことを確認
if (php_sapi_name() !== 'cli') {
    die("このスクリプトはWebブラウザからではなく、CLI (コマンドライン) から実行してください。\n");
}

// -----------------------------------------------------
// 初期設定とリソースの準備
// -----------------------------------------------------
try {
    // Loggerクラスのインスタンス化 (ログファイル名を指定)
    $logger = new Logger('duga_api_processing.log');
    $logger->log("Duga APIからのデータ取得とデータベース保存処理を開始します。");

    // データベース接続の確立
    $pdo = connectDB(); // db_config.phpで定義
    $logger->log("データベース接続に成功しました。");

    // Duga APIクライアントのインスタンス化
    $dugaApiClient = new DugaApiClient();

    // バルクインサートヘルパークラスのインスタンス化
    $dbBatchInserter = new DbBatchInsert($pdo);

} catch (Exception $e) {
    // 初期設定段階でのエラーをログに記録し、スクリプトを終了
    error_log("CLI初期設定エラー: " . $e->getMessage()); // PHPのエラーログに出力
    $logger->error("CLI初期設定中に致命的なエラーが発生しました: " . $e->getMessage()); // Dugaログに出力
    die("初期設定中にエラーが発生しました。ログを確認してください。\n");
}

// -----------------------------------------------------
// コマンドライン引数のパース
// -----------------------------------------------------
$cli_options = getopt("", ["start_date::", "end_date::", "keyword::", "genre_id::", "agentid::", "bannerid::", "adult::", "sort::"]);

$start_date = $cli_options['start_date'] ?? null;
$end_date   = $cli_options['end_date'] ?? null;
$keyword    = $cli_options['keyword'] ?? null;
$genre_id   = $cli_options['genre_id'] ?? null;
$agentid    = $cli_options['agentid'] ?? null;
$bannerid   = $cli_options['bannerid'] ?? null;
$adult      = $cli_options['adult'] ?? null;
$sort       = $cli_options['sort'] ?? null;

// agentid が指定されない場合、デフォルト値 '48043' を設定
if (empty($agentid)) {
    $agentid = '48043';
    $logger->log("Agent ID is not specified, defaulting to '48043'.");
}

// adult が指定されない場合、デフォルト値 '1' を設定
if (empty($adult)) {
    $adult = '1';
    $logger->log("Adult parameter is not specified, defaulting to '1'.");
}

// sort が指定されない場合、デフォルト値 'favorite' を設定
if (empty($sort)) {
    $sort = 'favorite';
    $logger->log("Sort parameter is not specified, defaulting to 'favorite'.");
}

// bannerid が指定されない場合、デフォルト値 '01' を設定
if (empty($bannerid)) {
    $bannerid = '01';
    $logger->log("Banner ID is not specified, defaulting to '01'.");
}

$logger->log("CLI Arguments: " . json_encode($cli_options));


// -----------------------------------------------------
// データ取得と保存のメインロジック
// -----------------------------------------------------
$current_page = 1; // 現在のページ番号 (ログと進捗表示用)
$total_processed_records = 0; // 全体の処理済みレコード数
$raw_data_buffer = []; // raw_api_data テーブルに挿入するためのバッファ
$products_buffer_temp = []; // products テーブルに挿入するためのデータと api_product_id の一時マッピング
$api_product_ids_in_batch = []; // 現在のバッチで処理するapi_product_idのリスト

$batch_size = 500; // データベースへのバルクインサートのチャンクサイズ (例: 500件ごと)
$api_records_per_request = 100; // APIが一度に返すレコード数 (Duga APIの制限に従う)
$api_source_name = 'duga'; // このAPIのソース名

try {
    while (true) {
        // offset は Duga APIの仕様に合わせて1から始まる
        $current_offset = ($current_page - 1) * $api_records_per_request + 1;
        $logger->log("Duga APIからアイテムを取得中... (ページ: {$current_page}, offset: {$current_offset}, 件数: {$api_records_per_request})");

        $additional_api_params = [];
        if ($start_date) $additional_api_params['release_date_from'] = $start_date;
        if ($end_date)   $additional_api_params['release_date_to'] = $end_date;
        if ($keyword)    $additional_api_params['keyword'] = $keyword;
        if ($genre_id)   $additional_api_params['genre_id'] = $genre_id;
        if ($agentid)    $additional_api_params['agentid'] = $agentid;
        if ($bannerid)   $additional_api_params['bannerid'] = $bannerid;
        if ($adult)      $additional_api_params['adult'] = $adult;
        if ($sort)       $additional_api_params['sort'] = $sort;

        // Duga APIからアイテムデータを取得
        $api_data_batch = $dugaApiClient->getItems($current_offset, $api_records_per_request, $additional_api_params);

        // APIからのデータが空の場合、全てのデータ取得が完了したと判断しループを終了
        if (empty($api_data_batch)) {
            $logger->log("Duga APIから追加のアイテムデータが取得できませんでした。全てのAPIデータの取得が完了しました。");
            break; // ループを抜ける
        }

        // 取得したAPIデータをraw_api_dataとproductsの両方のバッファに準備
        foreach ($api_data_batch as $api_record) {
            $content_id = $api_record['productid'] ?? null; 
            
            if (empty($content_id)) {
                $logger->error("警告: productid (または content_id/id) が空のためレコードをスキップします: " . json_encode($api_record));
                continue; // productid がないレコードはスキップ
            }

            // raw_api_data テーブル用のデータ準備
            $raw_data_entry = [
                'source_name'    => $api_source_name,
                'api_product_id' => $content_id,
                'row_json_data'  => json_encode($api_record),
                'fetched_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s')
            ];
            $raw_data_buffer[] = $raw_data_entry;
            $api_product_ids_in_batch[] = $content_id;

            // products テーブル用の初期データ準備
            $product_entry_temp = [
                'product_id'    => $content_id,
                'title'         => $api_record['title'] ?? null,
                'release_date'  => $api_record['opendate'] ?? $api_record['releasedate'] ?? null,
                'maker_name'    => $api_record['makername'] ?? null,
                'genre'         => isset($api_record['category'][0]['data']['name']) ? $api_record['category'][0]['data']['name'] : null, 
                'url'           => $api_record['affiliateurl'] ?? $api_record['url'] ?? null,
                'image_url'     => $api_record['jacketimage'][0]['large'] ?? $api_record['posterimage'][0]['large'] ?? null,
                'source_api'    => $api_source_name,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s')
            ];
            $products_buffer_temp[$content_id] = $product_entry_temp;
        }

        // バッファが指定したチャンクサイズに達したら、バルクインサートを実行
        if (count($raw_data_buffer) >= $batch_size) {
            $logger->log("バッファが{$batch_size}件に達しました。データベースへの挿入を開始します。");
            
            try {
                // 1. raw_api_data テーブルへの挿入
                $dbBatchInserter->insert('raw_api_data', $raw_data_buffer);
                $logger->log(count($raw_data_buffer) . "件の生データを 'raw_api_data' に挿入しました。");

                // 2. 挿入された raw_api_data のIDを取得し、products_buffer_tempに紐付ける
                $placeholders_sql = implode(',', array_fill(0, count($api_product_ids_in_batch), '?'));
                $stmt = $pdo->prepare("SELECT id, api_product_id FROM raw_api_data WHERE source_name = ? AND api_product_id IN ({$placeholders_sql})");
                $stmt->execute(array_merge([$api_source_name], $api_product_ids_in_batch));
                $raw_data_id_map = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $raw_data_id_map[$row['api_product_id']] = $row['id'];
                }
                $stmt->closeCursor();

                // products_buffer_temp に row_api_data_id を追加し、最終挿入用配列を作成
                $final_products_for_insert = [];
                foreach ($products_buffer_temp as $api_id => $product_data) {
                    if (isset($raw_data_id_map[$api_id])) {
                        $product_data['row_api_data_id'] = $raw_data_id_map[$api_id];
                        $final_products_for_insert[] = $product_data;
                    } else {
                        $logger->error("警告: api_product_id '{$api_id}' の row_api_data_id が見つかりませんでした。products テーブルに挿入されません。(最終バッチ)");
                    }
                }

                // 3. products テーブルへの挿入
                if (!empty($final_products_for_insert)) {
                    $dbBatchInserter->insert('products', $final_products_for_insert);
                    $total_processed_records += count($final_products_for_insert);
                    $logger->log(count($final_products_for_insert) . "件の商品データを 'products' に挿入しました。");
                } else {
                    $logger->log("products テーブルに挿入するデータがありませんでした。");
                }

                $logger->log("{$total_processed_records}件のデータをデータベースに正常に挿入しました。次のバッチ処理に進みます。");

            } catch (Exception $e) {
                $logger->error("データベース挿入中にエラーが発生しました: " . $e->getMessage());
                throw $e;
            }

            // バッファをクリア
            $raw_data_buffer = [];
            $products_buffer_temp = [];
            $api_product_ids_in_batch = [];
        }

        $current_page++;
        sleep(1); 
    }

    // ループ終了後、バッファに残っている未保存のデータを処理
    if (!empty($raw_data_buffer)) {
        $logger->log("処理終了。バッファに残っているデータをデータベースに挿入します。");
        
        try {
            // 1. raw_api_data テーブルへの挿入 (残り)
            $dbBatchInserter->insert('raw_api_data', $raw_data_buffer);
            $logger->log(count($raw_data_buffer) . "件の残りの生データを 'raw_api_data' に挿入しました。");

            // 2. 挿入された raw_api_data のIDを取得し、products_buffer_tempに紐付ける (残り)
            $placeholders_sql = implode(',', array_fill(0, count($api_product_ids_in_batch), '?'));
            $stmt = $pdo->prepare("SELECT id, api_product_id FROM raw_api_data WHERE source_name = ? AND api_product_id IN ({$placeholders_sql})");
            $stmt->execute(array_merge([$api_source_name], $api_product_ids_in_batch));
            $raw_data_id_map = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $raw_data_id_map[$row['api_product_id']] = $row['id'];
            }
            $stmt->closeCursor();

            $final_products_for_insert = [];
            foreach ($products_buffer_temp as $api_id => $product_data) {
                if (isset($raw_data_id_map[$api_id])) {
                    $product_data['row_api_data_id'] = $raw_data_id_map[$api_id];
                    $final_products_for_insert[] = $product_data;
                } else {
                    $logger->error("警告: api_product_id '{$api_id}' の row_api_data_id が見つかりませんでした。products テーブルに挿入されません。(最終バッチ)");
                }
            }

            // 3. products テーブルへの挿入 (残り)
            if (!empty($final_products_for_insert)) {
                $dbBatchInserter->insert('products', $final_products_for_insert);
                $total_processed_records += count($final_products_for_insert);
                $logger->log(count($final_products_for_insert) . "件の残りの商品データを 'products' に挿入しました。");
            } else {
                $logger->log("products テーブルに挿入する残りのデータがありませんでした。");
            }
            
            $logger->log("残りのデータも正常に挿入されました。");

        } catch (Exception $e) {
            $logger->error("データベース挿入中にエラーが発生しました。(最終バッチ): " . $e->getMessage());
            throw $e;
        }
    }

    $logger->log("Duga APIからの全データ取得とデータベース保存処理が完了しました。");
    $logger->log("合計処理済みレコード数: {$total_processed_records}件");

} catch (Exception $e) {
    $logger->error("Duga API処理中に致命的なエラーが発生しました: " . $e->getMessage());
    $logger->error("エラー発生箇所: ファイル " . $e->getFile() . " 行 " . $e->getLine());
} finally {
    $pdo = null;
    $logger->log("データベース接続を閉じました。スクリプトを終了します。");
}

exit(0);
?>
