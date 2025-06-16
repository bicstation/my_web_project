<?php
// app/cli/seed.php

// エラー表示を有効にする (開発用)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Composerのオートローダーを読み込む
// このファイル (seed.php) から見て vendor ディレクトリへの相対パスを正確に指定
require_once __DIR__ . '/../vendor/autoload.php';

// Dotenvライブラリを使って.envファイルをロード
// このファイル (seed.php) から見てプロジェクトルートへの相対パスを正確に指定
// プロジェクトルートは app ディレクトリの一つ上なので '../../'
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// 名前空間を使用するクラスをインポート
use App\Core\Logger;
use App\Core\Database;
use App\Database\Seeders\AdminUserSeeder; // 作成したSeederクラスをインポート

// データベース接続設定を.envから取得
$dbConfig = [
    'host'      => $_ENV['DB_HOST'] ?? 'localhost',
    'dbname'    => $_ENV['DB_NAME'] ?? 'web_project_db',
    'user'      => $_ENV['DB_USER'] ?? 'root',
    'pass'      => $_ENV['DB_PASS'] ?? 'password',
    'charset'   => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
];

try {
    // ロガーの初期化 (CLIスクリプト用のログファイル名にすることも可能)
    $logger = new Logger('cli_seed.log');

    // データベース接続の確立
    $database = new Database($dbConfig, $logger);
    $pdo = $database->getConnection(); // PDOインスタンスを取得

    // AdminUserSeederのインスタンスを作成し、依存関係を注入
    $seeder = new AdminUserSeeder($pdo, $logger);

    // Seederを実行
    echo "管理者ユーザーのシーディングを開始します...\n";
    $seeder->run();
    echo "シーディングが完了しました。\n";

} catch (Exception $e) {
    // 例外発生時のエラーハンドリングとログ出力
    $errorMsg = "シーディング中にエラーが発生しました: " . $e->getMessage();
    echo "致命的なエラー: " . $errorMsg . "\n";
    if (isset($logger)) {
        $logger->error($errorMsg);
    } else {
        error_log($errorMsg); // ロガーがない場合はPHPのエラーログに出力
    }
    exit(1); // エラーで終了
}
?>
