<?php
// public/click_redirect.php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use App\Core\Database;
use App\Core\Logger;
use App\Util\LinkClicker;

$logger = null;
$database = null;

try {
    $logger = new Logger('click_log.log'); // クリックログ専用のログファイル
    $database = new Database([
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'dbname' => $_ENV['DB_NAME'] ?? 'tiper',
        'user' => $_ENV['DB_USER'] ?? 'root',
        'pass' => $_ENV['DB_PASS'] ?? 'password',
        'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
    ], $logger);
    $pdo = $database->getConnection();

    $linkClicker = new LinkClicker($database, $logger);

} catch (Exception $e) {
    error_log("クリックリダイレクト初期設定エラー: " . $e->getMessage());
    if ($logger) {
        $logger->error("クリックリダイレクト初期設定中に致命的なエラーが発生しました: " . htmlspecialchars($e->getMessage()));
    }
    // エラー時は安全のためトップページにリダイレクトするか、エラーメッセージを表示
    header("Location: /");
    exit();
}

// -----------------------------------------------------
// クリックログ記録とリダイレクト
// -----------------------------------------------------
$product_id = $_GET['product_id'] ?? null;
$redirect_to_url = $_GET['redirect_to'] ?? null;

if (!$product_id || !$redirect_to_url) {
    // 必要なパラメータが不足している場合は、トップページなどにリダイレクト
    $logger->warning("クリックリダイレクト: product_id または redirect_to が不足しています。");
    header("Location: /");
    exit();
}

// URLの安全性を確保 (ホワイトリスト、スキームチェックなど)
// ここでは簡易的なチェックとしてURLが有効な形式か、http/httpsスキームかを確認
if (!filter_var($redirect_to_url, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//', $redirect_to_url)) {
    $logger->error("クリックリダイレクト: 無効なリダイレクトURLが指定されました。URL: " . htmlspecialchars($redirect_to_url));
    header("Location: /"); // 安全でないURLはリダイレクトしない
    exit();
}

try {
    // クリックログを記録
    // click_type は 'affiliate_link' などとする
    $linkClicker->logClick(
        $product_id,
        'affiliate_link', // クリックの種類
        $_SERVER['HTTP_REFERER'] ?? null, // 参照元URL
        $_SERVER['HTTP_USER_AGENT'] ?? null, // ユーザーエージェント
        $_SERVER['REMOTE_ADDR'] ?? null, // IPアドレス
        $redirect_to_url // リダイレクト先のURL
    );

    // ログ記録後にリダイレクト
    header("Location: " . $redirect_to_url);
    exit();

} catch (Exception $e) {
    $logger->error("クリックログ記録中にエラーが発生しました。しかしリダイレクトは試行します。エラー: " . $e->getMessage());
    // ログ記録に失敗しても、ユーザーを目的のページにリダイレクトすることは試みる
    header("Location: " . $redirect_to_url);
    exit();
}
?>