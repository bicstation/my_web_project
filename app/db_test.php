<?php
// データベース接続情報 (docker-compose.ymlで設定した情報)
$servername = "mysql"; // docker-compose.yml の services: mysql のサービス名
$username = "my_user";
$password = "my_password";
$dbname = "my_database";

// データベース接続
$conn = new mysqli($servername, $username, $password, $dbname);

// 接続チェック
if ($conn->connect_error) {
    die("データベース接続失敗: " . $conn->connect_error);
}
echo "データベース接続成功！<br>";

// テーブルの作成 (初回実行時のみ)
$sql_create_table = "CREATE TABLE IF NOT EXISTS messages (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message VARCHAR(255) NOT NULL,
    reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
if ($conn->query($sql_create_table) === TRUE) {
    echo "テーブル 'messages' が存在しない場合は作成されました。<br>";
} else {
    echo "テーブル作成エラー: " . $conn->error . "<br>";
}

// データの挿入 (実行するたびに追加)
$message_content = "Hello Docker World from PHP!";
$sql_insert_data = "INSERT INTO messages (message) VALUES ('" . $message_content . "')";
if ($conn->query($sql_insert_data) === TRUE) {
    echo "新しいレコードが正常に挿入されました。<br>";
} else {
    echo "レコード挿入エラー: " . $conn->error . "<br>";
}

// データの取得と表示
echo "<h2>messages テーブルのデータ:</h2>";
$sql_select_data = "SELECT id, message, reg_date FROM messages";
$result = $conn->query($sql_select_data);

if ($result->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>メッセージ</th><th>登録日時</th></tr>";
    while($row = $result->fetch_assoc()) {
        echo "<tr><td>" . $row["id"]. "</td><td>" . $row["message"]. "</td><td>" . $row["reg_date"]. "</td></tr>";
    }
    echo "</table>";
} else {
    echo "データがありません。";
}

$conn->close();
?>