<?php
// my_web_project/app/public/login.php

require __DIR__ . '/includes/config.php';

$username_err = $password_err = "";
$username = $password = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // ユーザー名が空でないかチェック
    if (empty(trim($_POST["username"]))) {
        $username_err = "ユーザー名を入力してください。";
    } else {
        $username = trim($_POST["username"]);
    }

    // パスワードが空でないかチェック
    if (empty(trim($_POST["password"]))) {
        $password_err = "パスワードを入力してください。";
    } else {
        $password = trim($_POST["password"]);
    }

    // 入力値が検証済みであれば認証を試みる
    if (empty($username_err) && empty($password_err)) {
        $sql = "SELECT id, username, password_hash FROM users WHERE username = ?";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_username);
            $param_username = $username;

            if ($stmt->execute()) {
                $stmt->store_result();

                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($id, $username, $hashed_password);
                    if ($stmt->fetch()) {
                        if (password_verify($password, $hashed_password)) {
                            // パスワードが正しい場合、セッションを開始
                            // session_start(); // config.php で開始済みの場合もあるが念のため

                            $_SESSION["loggedin"] = true;
                            $_SESSION["user_id"] = $id;
                            $_SESSION["username"] = $username;

                            // ダッシュボードページにリダイレクト
                            header("Location: dashboard.php");
                            exit();
                        } else {
                            $password_err = "入力されたパスワードが正しくありません。";
                        }
                    }
                } else {
                    $username_err = "指定されたユーザー名のレコードは見つかりませんでした。";
                }
            } else {
                echo "エラーが発生しました。後でもう一度お試しください。";
            }
            $stmt->close();
        }
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ログイン</title>
    <style>
        body { font: 14px sans-serif; text-align: center; }
        .wrapper { width: 360px; padding: 20px; margin: 50px auto; border: 1px solid #ddd; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; }
        .form-group input[type="text"], .form-group input[type="password"] { width: calc(100% - 22px); padding: 10px; border: 1px solid #ccc; border-radius: 3px; }
        .form-group .help-block { color: red; font-size: 0.9em; }
        .btn { padding: 10px 20px; background-color: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer; }
        .btn:hover { background-color: #0056b3; }
    </style>
</head>
<body>
    <div class="wrapper">
        <h2>ログイン</h2>
        <p>ログイン情報を入力してください。</p>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label>ユーザー名</label>
                <input type="text" name="username" value="<?php echo $username; ?>">
                <span class="help-block"><?php echo $username_err; ?></span>
            </div>
            <div class="form-group">
                <label>パスワード</label>
                <input type="password" name="password">
                <span class="help-block"><?php echo $password_err; ?></span>
            </div>
            <div class="form-group">
                <input type="submit" class="btn" value="ログイン">
            </div>
            <p>アカウントをお持ちではありませんか？ <a href="create_users_table.php">ユーザーを作成</a>してください。</p>
        </form>
    </div>
</body>
</html>