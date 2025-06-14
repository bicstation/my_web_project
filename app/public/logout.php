<?php
// C:\doc\my_web_project\app\public\logout.php
// ログアウト処理

// 共通初期化ファイルを読み込む（セッションハンドラ設定とsession_start()を含む）
require_once __DIR__ . '/init.php';

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
