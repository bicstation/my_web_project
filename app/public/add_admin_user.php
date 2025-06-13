<?php
// C:\doc\my_web_project\app\public\add_admin_user.php

// 以下の警告が発生する場合の注意点:
// "ini_set(): Session ini settings cannot be changed after headers have already been sent"
// "session_set_save_handler(): Session save handler cannot be changed after headers have already been sent"
// "session_start(): Session cannot be started after headers have already been sent"
// これらの警告は、PHPがヘッダーを送信（つまり、何らかの出力がブラウザに送られた後）した後に、
// セッション関連の設定変更やセッション開始を行おうとすると発生します。
//
// 主な原因として考えられるのは：
// 1. このファイル (add_admin_user.php) の <?php タグの前に、余分な空白、改行、またはBOM (Byte Order Mark) がある。
// 2. require_once で読み込まれるファイル (特に vendor/autoload.php や init.php) に、同様の不要な出力がある。
// 3. アプリケーションコード内で echo や print などで明示的な出力が行われる前に、セッション開始処理が呼ばれていない。
// これらの問題を解決するには、すべてのPHPファイルの先頭が <?php で始まり、その前に何も文字がないことを確認してください。
// 特に、ライブラリファイルには終了タグを書かないのがPHPのベストプラクティスです。

// Composerのオートローダーを読み込む
require_once __DIR__ . '/../../vendor/autoload.php';

// Dotenvライブラリを使って.envファイルをロード
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// 名前空間を使用するクラスをインポート
use App\Core\Logger;
use App\Core\Database;
// PDOException はPHPのグローバルクラスであるため、このuseステートメントは不要です。

// 共通初期化ファイルを読み込む（セッションハンドラ設定とsession_start()を含む）
// init.php 内で Composer のオートローダーを読み込んだり、.env をロードしたりする必要はなくなります。
require_once __DIR__ . '/init.php';

// データベース接続設定を.envから取得
$dbConfig = [
    'host'    => $_ENV['DB_HOST'] ?? 'localhost',
    'dbname'  => $_ENV['DB_NAME'] ?? 'web_project_db',
    'user'    => $_ENV['DB_USER'] ?? 'root',
    'pass'    => $_ENV['DB_PASS'] ?? 'password',
    'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
];

$message = ""; // 成功・失敗メッセージを格納する変数

try {
    // ロガーの初期化
    $logger = new Logger('add_admin_user.log'); // このスクリプト専用のログファイル

    // データベース接続の確立
    $database = new Database($dbConfig, $logger);
    $pdo = $database->getConnection(); // PDOインスタンスを取得

    // 環境変数からデータベースパスワードを取得し、デフォルトパスワードを設定
    $admin_username = "admin";
    $admin_email = "admin@tipers.live";
    // .env の DB_PASSWORD を管理者パスワードとして使用
    $admin_password = $_ENV['DB_PASSWORD'] ?? 'password'; // デフォルトパスワードを設定

    $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);

    // ユーザーが既に存在するかチェック (emailでチェック)
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
    $stmt_check->bindParam(':email', $admin_email, PDO::PARAM_STR);
    $stmt_check->execute();
    $user_exists = $stmt_check->fetchColumn();

    if ($user_exists == 0) {
        // ユーザーが存在しない場合のみ追加
        // usersテーブルのスキーマに合わせて 'role' カラムに 'admin' を設定
        $stmt_insert = $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (:username, :email, :password_hash, :role)");
        $stmt_insert->bindParam(':username', $admin_username, PDO::PARAM_STR);
        $stmt_insert->bindParam(':email', $admin_email, PDO::PARAM_STR);
        $stmt_insert->bindParam(':password_hash', $hashed_password, PDO::PARAM_STR);
        $admin_role = 'admin'; // 'role' カラムに設定する値
        $stmt_insert->bindParam(':role', $admin_role, PDO::PARAM_STR);

        if ($stmt_insert->execute()) {
            $message = "<div class='alert alert-success'>管理者ユーザー ('admin' / 'admin@tipers.live') が正常に追加されました。パスワードは '" . htmlspecialchars($admin_password) . "' です。</div>";
            $logger->log("管理者ユーザー '{$admin_email}' が正常に追加されました。");
        } else {
            $errorInfo = $stmt_insert->errorInfo();
            $message = "<div class='alert alert-danger'>管理者ユーザーの追加に失敗しました: " . htmlspecialchars($errorInfo[2]) . "</div>";
            $logger->error("管理者ユーザーの追加に失敗しました: " . $errorInfo[2]);
        }
    } else {
        $message = "<div class='alert alert-info'>管理者ユーザー ('admin@tipers.live') は既に存在します。</div>";
        $logger->log("管理者ユーザー '{$admin_email}' は既に存在します。");
    }

} catch (PDOException $e) { // PDOException は use なしで直接使えます
    $message = "<div class='alert alert-danger'>データベース操作エラー: " . htmlspecialchars($e->getMessage()) . "</div>";
    error_log("Add Admin User DB error: " . $e->getMessage()); // PHPのエラーログに出力
    if (isset($logger)) { // ロガーが初期化されている場合のみ利用
        $logger->error("Add Admin User DB error: " . $e->getMessage());
    }
} catch (Exception $e) {
    $message = "<div class='alert alert-danger'>アプリケーションエラー: " . htmlspecialchars($e->getMessage()) . "</div>";
    error_log("Add Admin User application error: " . $e->getMessage());
    if (isset($logger)) {
        $logger->error("Add Admin User application error: " . $e->getMessage());
    }
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理者ユーザー追加</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; background-color: #f8f9fa; }
        .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); max-width: 600px; margin: 50px auto; }
        h1 { color: #007bff; margin-bottom: 25px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h1>管理者ユーザーの追加</h1>
        <?php echo $message; ?>
        <p>このページは、管理者ユーザー (`admin@tipers.live`) をデータベースに登録します。</p>
        <p>パスワードは **`<?php echo htmlspecialchars($admin_password); ?>`** (または `docker-compose.yml` で設定した `DB_PASSWORD`) です。</p>
        <p>既にユーザーが存在する場合は、再度追加はされません。</p>
        <a href="/login.php" class="btn btn-primary mt-3">ログインページへ</a>
        <a href="/" class="btn btn-secondary mt-3 ms-2">ホームへ戻る</a>
    </div>
</body>
</html>
