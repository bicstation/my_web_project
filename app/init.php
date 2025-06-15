<?php
// C:\project\my_web_project\app\init.php

// -----------------------------------------------------
// 1. Composer オートローダーの読み込み
// -----------------------------------------------------
// プロジェクトルートからのパスを適切に設定してください。
require_once __DIR__ . '/../vendor/autoload.php';

// -----------------------------------------------------
// 2. 環境変数の読み込み (.env)
// -----------------------------------------------------
// dotenv を使用して環境変数をロード
// Dotenv::createImmutable(__DIR__ . '/..') の第二引数に .env ファイルのディレクトリを指定
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../'); // プロジェクトルートを指すように修正
$dotenv->load();

// -----------------------------------------------------
// 3. セッションの開始と管理 (App\Core\Session クラスを使用)
// -----------------------------------------------------
use App\Core\Session;

Session::start(); // セッションを開始
Session::checkActivity(); // セッション活動をチェックし、必要に応じてタイムアウト処理
// 認証が必要なページの場合、ここでログインチェックを行うこともできます
// 例: if (!Session::isLoggedIn() && basename($_SERVER['PHP_SELF']) !== 'login.php') {
//         header('Location: login.php');
//         exit();
//     }

// -----------------------------------------------------
// 4. その他のグローバルな設定やインスタンス化
// -----------------------------------------------------
// ロガーのインスタンス化 (必要であれば)
// use App\Core\Logger;
// $globalLogger = new Logger('application.log');
// $globalLogger->log("Application initialized.");

// データベース設定 (環境変数から取得)
// データベース接続の確立は、必要なページで遅延させて行うか、
// アプリケーション全体で共有するデータベース接続クラスを作成することもできます。
$dbConfig = [
    'host'    => $_ENV['DB_HOST'] ?? 'localhost',
    'dbname'  => $_ENV['DB_NAME'] ?? 'web_project_db',
    'user'    => $_ENV['DB_USER'] ?? 'root',
    'pass'    => $_ENV['DB_PASS'] ?? 'password',
    'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
];

// データベース接続のインスタンス化は、各コンポーネントで行うのが一般的ですが、
// グローバルにアクセス可能にする場合はここでインスタンス化しても良いでしょう。
// use App\Core\Database;
// $database = new Database($dbConfig, $globalLogger ?? null); // ロガーがあれば渡す

// その他のグローバル変数や定数など
define('APP_ROOT', __DIR__ . '/..'); // アプリケーションのルートパス