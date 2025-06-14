<?php
// C:\doc\my_web_project\app\includes\db_config.php
// データベース接続設定

/**
 * データベースに接続し、PDOオブジェクトを返します。
 * @return PDO データベース接続を表すPDOオブジェクト
 * @throws PDOException 接続に失敗した場合
 */
function connectDB() {
    // .envファイルからデータベース接続情報を取得
    // getenv() はphp-fpmサービスの設定で環境変数が渡されていることを前提とします
    $db_host = getenv('DB_HOST');
    $db_name = getenv('DB_NAME');
    $db_user = getenv('DB_USER');
    $db_password = getenv('DB_PASSWORD');

    // 環境変数が取得できない場合のデフォルト値（開発用、本番では非推奨）
    if (empty($db_host)) $db_host = 'mysql';
    if (empty($db_name)) $db_name = 'tiper';
    if (empty($db_user)) $db_user = 'tiper'; // または tiper_db
    if (empty($db_password)) $db_password = '1492nabe'; // またはご自身のパスワード

    // デバッグログ: 実際に接続しようとしている情報を出力
    error_log("DB_CONFIG: Connecting to: host={$db_host}, dbname={$db_name}, user={$db_user}");

    $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // エラーモードを例外に設定
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,     // デフォルトのフェッチモードを連想配列に設定
        PDO::ATTR_EMULATE_PREPARES   => false,                // プリペアドステートメントのエミュレーションを無効化
    ];

    try {
        $pdo = new PDO($dsn, $db_user, $db_password, $options);
        return $pdo;
    } catch (PDOException $e) {
        // エラーログに出力し、スクリプトを終了
        error_log("Database connection error: " . $e->getMessage());
        // 開発中はdie()で詳細を表示しても良いが、本番環境では避ける
        die("データベース接続に失敗しました。詳細はログを確認してください。");
    }
}
?>
