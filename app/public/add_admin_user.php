<?php
// C:\doc\my_web_project\app\public\add_admin_user.php

// 共通初期化ファイルを読み込む（セッションハンドラ設定とsession_start()を含む）
require_once __DIR__ . '/init.php';

// データベース接続設定ファイルを読み込む
require_once __DIR__ . '/../includes/db_config.php';

$message = ""; // 成功・失敗メッセージを格納する変数

try {
    $pdo = connectDB(); // db_config.phpで定義されているデータベース接続関数

    // 環境変数からデータベース接続情報を取得
    $db_user_env = getenv('DB_USER'); // docker-compose.ymlでphpサービスに設定されているDB_USER
    $db_password_env = getenv('DB_PASSWORD'); // docker-compose.ymlでphpサービスに設定されているDB_PASSWORD

    // ユーザー名とパスワードをハードコード
    $admin_username = "admin";
    $admin_email = "admin@tipers.live";
    $admin_password = "1492nabe"; // デフォルトパスワード（本番環境では使用しない）

    // 環境変数があればそちらを優先 (オプション)
    if (!empty($db_user_env)) {
        // $admin_username = $db_user_env; // ユーザー名は固定のadminを維持
    }
    if (!empty($db_password_env)) {
        $admin_password = $db_password_env; // パスワードは環境変数を使用
    }

    $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);

    // ユーザーが既に存在するかチェック
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
    $stmt_check->bindParam(':email', $admin_email, PDO::PARAM_STR);
    $stmt_check->execute();
    $user_exists = $stmt_check->fetchColumn();

    if ($user_exists == 0) {
        // ユーザーが存在しない場合のみ追加
        $stmt_insert = $pdo->prepare("INSERT INTO users (username, email, password_hash, is_admin) VALUES (:username, :email, :password_hash, 1)");
        $stmt_insert->bindParam(':username', $admin_username, PDO::PARAM_STR);
        $stmt_insert->bindParam(':email', $admin_email, PDO::PARAM_STR);
        $stmt_insert->bindParam(':password_hash', $hashed_password, PDO::PARAM_STR);

        if ($stmt_insert->execute()) {
            $message = "<div class='alert alert-success'>管理者ユーザー ('admin' / 'admin@tipers.live') が正常に追加されました。パスワードは '" . htmlspecialchars($admin_password) . "' です。</div>";
        } else {
            $message = "<div class='alert alert-danger'>管理者ユーザーの追加に失敗しました: " . $stmt_insert->errorInfo()[2] . "</div>";
        }
    } else {
        $message = "<div class='alert alert-info'>管理者ユーザー ('admin@tipers.live') は既に存在します。</div>";
    }

} catch (PDOException $e) {
    $message = "<div class='alert alert-danger'>データベース操作エラー: " . $e->getMessage() . "</div>";
    error_log("Add Admin User DB error: " . $e->getMessage());
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
        <p>パスワードは **`adminpassword`** (または `docker-compose.yml` で設定した `DB_PASSWORD`) です。</p>
        <p>既にユーザーが存在する場合は、再度追加はされません。</p>
        <a href="/login.php" class="btn btn-primary mt-3">ログインページへ</a>
        <a href="/" class="btn btn-secondary mt-3 ms-2">ホームへ戻る</a>
    </div>
</body>
</html>
