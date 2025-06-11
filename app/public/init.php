<?php
// C:\doc\my_web_project\app\public\init.php
// アプリケーション全体の共通初期化ファイル

// エラー報告を有効にする (開発用 - 本番環境では適宜調整またはphp.iniで設定)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// セッションハンドラを読み込み、データベースセッションを使用するように設定
require_once __DIR__ . '/session_handler.php'; // カスタムセッションハンドラファイルを読み込む

// ★追加: セッションのシリアライズハンドラを明示的に 'php' に設定
// これにより、PHPがセッションデータを読み書きする際のフォーマットが統一されます。
ini_set('session.serialize_handler', 'php');

session_set_save_handler(new DBSessionHandler(), true); // カスタムハンドラを登録

// PHPのデフォルトセッションGCを無効化（データベースでのGCを優先）
ini_set('session.gc_probability', 0);
ini_set('session.gc_divisor', 1);

// セッションを開始 (必ず、セッションハンドラ設定の直後に配置)
// これを呼ぶことで、DBSessionHandler の open, read メソッドが呼び出される
session_start();

// ログアウト処理後にセッションが正しくクリアされたことを確認するため、
// ログアウトページ以外ではセッションの中身をログに出力する
if (basename($_SERVER['PHP_SELF']) !== 'logout.php') {
    error_log("INIT.PHP - SESSION DUMP: " . print_r($_SESSION, true));
}

// データベース接続設定ファイルもここで読み込むことを推奨
// require_once __DIR__ . '/../includes/db_config.php';
// （各ファイルでconnectDB()を呼ぶ際にrequire_onceされるため、ここでは不要と判断）

?>
