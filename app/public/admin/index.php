<?php
// C:\doc\my_web_project\app\admin\index.php

// エラー報告を有効にする (開発用)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 環境変数からデータベース接続情報を取得
$db_host = getenv('DB_HOST');
$db_name = getenv('DB_NAME');
$db_user = getenv('DB_USER');
$db_password = getenv('DB_PASSWORD');

// データベース接続
$conn = new mysqli($db_host, $db_user, $db_password, $db_name);

// 接続エラーの確認
if ($conn->connect_error) {
    die("データベース接続エラー: " . $conn->connect_error);
}

echo "<!DOCTYPE html>";
echo "<html lang='ja'>";
echo "<head>";
echo "    <meta charset='UTF-8'>";
echo "    <meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "    <title>管理パネル - データ一覧</title>";
echo "    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>";
echo "    <style>";
echo "        body { padding: 20px; background-color: #f8f9fa; }";
echo "        .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }";
echo "        h1 { color: #007bff; margin-bottom: 25px; }";
echo "        .table th { background-color: #e9ecef; }";
echo "    </style>";
echo "</head>";
echo "<body>";
echo "<div class='container'>";
echo "    <h1>MySQLデータ一覧</h1>";

// 例として 'users' テーブルを想定 (もし存在しない場合は作成する必要があります)
// 実際には、管理したいデータのテーブル名に変更してください
$sql = "SELECT id, name, email, created_at FROM users"; // usersテーブルが存在しない場合、ここでエラーになります
$result = $conn->query($sql);

if ($result) {
    if ($result->num_rows > 0) {
        echo "<table class='table table-bordered table-striped'>";
        echo "<thead><tr><th>ID</th><th>名前</th><th>Eメール</th><th>作成日時</th></tr></thead>";
        echo "<tbody>";
        while($row = $result->fetch_assoc()) {
            echo "<tr><td>" . $row["id"]. "</td><td>" . $row["name"]. "</td><td>" . $row["email"]. "</td><td>" . $row["created_at"]. "</td></tr>";
        }
        echo "</tbody></table>";
    } else {
        echo "<p class='alert alert-info'>データが見つかりませんでした。</p>";
    }
} else {
    // クエリ実行エラーの場合
    echo "<p class='alert alert-danger'>テーブルからデータを取得できませんでした: " . $conn->error . "</p>";
    echo "<p class='alert alert-info'>'users' テーブルが存在しない場合、このエラーが出ます。後でテーブルを作成します。</p>";
}

// 接続を閉じる
$conn->close();

echo "</div>";
echo "</body>";
echo "</html>";
?>
