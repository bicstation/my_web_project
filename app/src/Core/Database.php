<?php
// C:\project\my_web_project\app\src\Core\Database.php
namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private PDO $pdo;
    private array $config;
    private ?Logger $logger; // オプションでLoggerを受け取る

    /**
     * Database constructor.
     * @param array $config データベース接続設定 (db_config.php の内容を連想配列で渡すことを想定)
     * 例: ['host' => 'localhost', 'dbname' => 'mydb', 'user' => 'root', 'pass' => '']
     * @param Logger|null $logger ロギングのためのLoggerインスタンス (任意)
     */
    public function __construct(array $config, ?Logger $logger = null)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->connect();
    }

    /**
     * データベースへの接続を確立します。
     * 接続に失敗した場合はPDOExceptionをスローします。
     */
    private function connect(): void
    {
        $host = $this->config['host'] ?? 'localhost';
        $dbname = $this->config['dbname'] ?? 'web_project_db';
        $user = $this->config['user'] ?? 'root';
        $pass = $this->config['pass'] ?? 'password'; // デフォルトパスワードを設定
        $charset = $this->config['charset'] ?? 'utf8mb4';

        $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
        $options = [
            PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION, // エラーモードを例外に設定
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,     // デフォルトのフェッチモードを連想配列に設定
            PDO::ATTR_EMULATE_PREPARES  => false,                // プリペアドステートメントのエミュレーションを無効にする (セキュリティとパフォーマンスのため)
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
            // 修正: log() メソッドを info() メソッドに変更
            $this->logger?->info("データベース接続に成功しました。");
        } catch (PDOException $e) {
            $errorMessage = "データベース接続エラー: " . $e->getMessage();
            $this->logger?->error($errorMessage);
            // 接続エラーは致命的なので、例外を再スローします。
            throw new PDOException($errorMessage, (int)$e->getCode());
        }
    }

    /**
     * PDOインスタンスを返します。
     * @return PDO
     */
    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    /**
     * トランザクションを開始します。
     * @return bool
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * トランザクションをコミットします。
     * @return bool
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * トランザクションをロールバックします。
     * @return bool
     */
    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }
}

