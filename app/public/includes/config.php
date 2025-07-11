<?php
// セッションを開始
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// データベース接続情報
define('DB_HOST', getenv('DB_HOST'));
define('DB_USER', getenv('DB_USER'));
define('DB_PASSWORD', getenv('DB_PASSWORD'));
define('DB_NAME', getenv('DB_NAME'));

// データベース接続の確立
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

// 接続エラーをチェック
if ($conn->connect_error) {
    die("データベース接続失敗: " . $conn->connect_error);
}

// PHPMailerの設定 (あなたのGmail情報に置き換える)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 465); // SSLの場合
define('SMTP_USERNAME', 'bicstation@gmail.com');
define('SMTP_PASSWORD', 'nkkdbufmmjnkfwvh'); // 例: nkkd bufm mjnk fwvh
define('SENDER_EMAIL', 'bicstation@gmail.com');
define('SENDER_NAME', 'Tiper Live');

// ユーザーがログインしているかチェックする関数
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// ログインしていない場合はログインページにリダイレクトする関数
function redirect_if_not_logged_in() {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit();
    }
}



?>