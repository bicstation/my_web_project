<?php
// database/seeders/AdminUserSeeder.php
namespace App\Database\Seeders;

use App\Core\Database; // Databaseクラスをインポート
use App\Core\Logger; // Loggerクラスをインポート
use PDO;

class AdminUserSeeder
{
    private $pdo;
    private $logger;

    public function __construct(PDO $pdo, Logger $logger)
    {
        $this->pdo = $pdo;
        $this->logger = $logger;
    }

    public function run()
    {
        $admin_username = "admin";
        $admin_email = "admin@tiper.live";
        // .env から直接読み込むのではなく、環境変数または設定ファイルから安全に取得
        $admin_password = $_ENV['ADMIN_DEFAULT_PASSWORD'] ?? 'secure_default_password'; // 新しい環境変数

        $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);

        $stmt_check = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
        $stmt_check->bindParam(':email', $admin_email, PDO::PARAM_STR);
        $stmt_check->execute();
        $user_exists = $stmt_check->fetchColumn();

        if ($user_exists == 0) {
            $stmt_insert = $this->pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (:username, :email, :password_hash, :role)");
            $stmt_insert->bindParam(':username', $admin_username, PDO::PARAM_STR);
            $stmt_insert->bindParam(':email', $admin_email, PDO::PARAM_STR);
            $stmt_insert->bindParam(':password_hash', $hashed_password, PDO::PARAM_STR);
            $admin_role = 'admin';
            $stmt_insert->bindParam(':role', $admin_role, PDO::PARAM_STR);

            if ($stmt_insert->execute()) {
                $this->logger->info("管理者ユーザー '{$admin_email}' が正常に追加されました。");
                echo "管理者ユーザー '{$admin_email}' が正常に追加されました。\n";
            } else {
                $errorInfo = $stmt_insert->errorInfo();
                $this->logger->error("管理者ユーザーの追加に失敗しました: " . $errorInfo[2]);
                echo "エラー: 管理者ユーザーの追加に失敗しました: " . $errorInfo[2] . "\n";
            }
        } else {
            $this->logger->info("管理者ユーザー '{$admin_email}' は既に存在します。");
            echo "管理者ユーザー '{$admin_email}' は既に存在します。\n";
        }
    }
}
?>