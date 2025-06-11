<?php
// C:\doc\my_web_project\app\public\logout.php
// ログアウト処理

session_start(); // セッションを開始

// 全てのセッション変数をクリア
$_SESSION = array();

// セッションクッキーを削除
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// セッションを破棄
session_destroy();

// ログインページまたはトップページにリダイレクト
header("Location: /login.php");
exit();
?>
