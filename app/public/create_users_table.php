<?php
// my_web_project/app/public/create_users_table.php

// このファイルをブラウザで実行すると、usersテーブルが作成され、テストユーザーが追加されます。
// データベース接続情報とテーブル定義は、includes/config.php で定義されています。

// 共通設定ファイルを読み込む
require __DIR__ . '/includes/config.php';

// users テーブルが存在しない場合は作成
$sql_create_users = "
CREATE TABLE IF NOT EXISTS users (
id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
username VARCHAR(50) NOT NULL UNIQUE,
email VARCHAR(100) NOT NULL UNIQUE,
password_hash VARCHAR(255) NOT NULL,
two_factor_code VARCHAR(10) DEFAULT NULL,
two_factor_expires_at DATETIME DEFAULT NULL,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
";

if ($conn->query($sql_create_users) === TRUE) {
echo "テーブル 'users' が存在しない場合は作成されました。&lt;br>";
} else {
echo "テーブル 'users' の作成中にエラーが発生しました: " . $conn->error . "&lt;br>";
}

// テストユーザーの追加 (パスワードはハッシュ化)
// 例: user@example.com / password123
$test_username = "testuser";
$test_email = "test@example.com"; // 2FAコードを受け取るためのメールアドレス
$test_password_raw = "password123";
$test_password_hash = password_hash($test_password_raw, PASSWORD_DEFAULT);

// ユーザーが存在しない場合のみ追加
$sql_check_user = "SELECT id FROM users WHERE username = '$test_username'";
$result_check_user = $conn->query($sql_check_user);

if ($result_check_user->num_rows == 0) {
$sql_insert_user = "INSERT INTO users (username, email, password_hash) VALUES ('$test_username', '$test_email', '$test_password_hash')";
if ($conn->query($sql_insert_user) === TRUE) {
echo "テストユーザー '$test_username' が追加されました。&lt;br>";
} else {
echo "テストユーザーの追加中にエラーが発生しました: " . $conn->error . "&lt;br>";
}
} else {
echo "テストユーザー '$test_username' は既に存在します。&lt;br>";
}

$conn->close();
?>