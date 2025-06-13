<?php
// C:\project\my_web_project\app\public\track_click.php

// ★重要: デバッグ用にPHPのエラー表示を強制的にオンにする (本番環境では必ずオフにしてください)
error_reporting(E_ALL);
ini_set('display_errors', 1); // ブラウザに出力
ini_set('log_errors', 1);     // エラーをログファイルに記録

// ★ログディレクトリのパスを定義し、存在しない場合は作成
$log_dir = __DIR__ . '/../../app/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0777, true); // 0777は開発用。本番では適切な権限を設定
    // エラーロギングが初期化される前にディレクトリ作成エラーが発生しないよう、手動でログ
    error_log("Log directory created: " . $log_dir);
}
ini_set('error_log', $log_dir . '/php_error.log'); // 専用のエラーログファイル

// Composerのオートローダーを読み込む
require_once __DIR__ . '/../../vendor/autoload.php';

// Dotenvライブラリをロード
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// 名前空間を使用するクラスをインポート
use App\Core\Logger;
use App\Core\Database;
// use PDOException; // グローバル名前空間のPDOExceptionはuse不要だが、明示的に書いても問題ない

// レスポンスヘッダーをJSONに設定
header('Content-Type: application/json');

$logger = null;
$pdo = null;

try {
    // ロガーの初期化
    $logger = new Logger('click_tracking.log'); // このスクリプト専用のログ
    $logger->log("track_click.php が呼び出されました。");

    // データベース設定の取得
    $dbConfig = [
        'host'    => $_ENV['DB_HOST'] ?? 'localhost',
        'dbname'  => $_ENV['DB_NAME'] ?? 'web_project_db',
        'user'    => $_ENV['DB_USER'] ?? 'root',
        'pass'    => $_ENV['DB_PASS'] ?? 'password', // ★修正: DB_PASS を使用
        'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
    ];

    // データベース接続の確立
    $database = new Database($dbConfig, $logger);
    $pdo = $database->getConnection();
    $logger->log("データベース接続に成功しました。");

    // POSTされたJSONデータを受け取る
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $errorMessage = "無効なJSONデータ: " . json_last_error_msg();
        $logger->error("track_click.php - " . $errorMessage . " Input: " . ($input ? $input : '(empty input)'));
        throw new Exception($errorMessage);
    }

    // 必須データのチェック
    $product_id = $data['product_id'] ?? null;
    $click_type = $data['click_type'] ?? null;
    $referrer = $data['referrer'] ?? null;
    $user_agent = $data['user_agent'] ?? null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'; // サーバーサイドでIPを取得

    if (!$product_id || !$click_type) {
        $errorMessage = "必須データ (product_id または click_type) が不足しています。受信データ: " . json_encode($data);
        $logger->error("track_click.php - " . $errorMessage);
        throw new Exception($errorMessage);
    }

    // データベースにクリック情報を挿入
    $stmt = $pdo->prepare("INSERT INTO link_clicks (product_id, click_type, referrer, user_agent, ip_address, clicked_at) VALUES (:product_id, :click_type, :referrer, :user_agent, :ip_address, NOW())");

    $stmt->execute([
        ':product_id' => $product_id,
        ':click_type' => $click_type,
        ':referrer'   => $referrer,
        ':user_agent' => $user_agent,
        ':ip_address' => $ip_address,
    ]);

    $logger->log("クリックイベントをデータベースに記録しました: Product ID: {$product_id}, Type: {$click_type}, IP: {$ip_address}");
    echo json_encode(['status' => 'success', 'message' => 'クリックイベントが正常に記録されました。']);

} catch (PDOException $e) {
    $errorMessage = "データベースエラー: " . $e->getMessage();
    // ログ書き込み処理自体でエラーが出ないよう、Loggerが使用できない場合も想定してerror_logも使用
    error_log("track_click.php - PDOException: " . $errorMessage . " SQLSTATE: " . $e->getCode() . " Error Info: " . json_encode($pdo->errorInfo()));
    if ($logger) {
        $logger->error("track_click.php - PDOException: " . $errorMessage . " SQLSTATE: " . $e->getCode() . " Error Info: " . json_encode($pdo->errorInfo()));
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $errorMessage]);
} catch (Exception $e) {
    $errorMessage = "アプリケーションエラー: " . $e->getMessage();
    error_log("track_click.php - Application Exception: " . $errorMessage . " File: " . $e->getFile() . " Line: " . $e->getLine());
    if ($logger) {
        $logger->error("track_click.php - Application Exception: " . $errorMessage . " File: " . $e->getFile() . " Line: " . $e->getLine());
    }
    http_response_code(400); // Bad Request for client-side issues
    echo json_encode(['status' => 'error', 'message' => $errorMessage]);
} finally {
    // データベース接続を閉じる (PDOオブジェクトをnullに設定)
    $pdo = null; // ここで明示的に接続を閉じる
}
