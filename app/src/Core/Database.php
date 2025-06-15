<?php
// C:\project\my_web_project\app\Core\Database.php

namespace App\Core;

use PDO;
use PDOException;
use Exception; // 基本的なExceptionクラスもuseしておく

// データベース接続を管理するクラス
class Database
{
    private $host;
    private $dbname;
    private $user;
    private $pass;
    private $charset;
    private $pdo; // PDOインスタンス
    private $logger; // ロガーインスタンス

    public function __construct(array $config, Logger $logger)
    {
        $this->host = $config['host'];
        $this->dbname = $config['dbname'];
        $this->user = $config['user'];
        $this->pass = $config['pass'];
        $this->charset = $config['charset'];
        $this->logger = $logger; // ロガーを受け取る

        $this->connect(); // コンストラクタで接続を試みる
    }

    private function connect()
    {
        $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // エラー時に例外をスロー
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // デフォルトのフェッチモードを連想配列に設定
            PDO::ATTR_EMULATE_PREPARES   => false,                    // ネイティブプリペアドステートメントを使用 (セキュリティとパフォーマンスのため)
        ];

        try {
            $this->pdo = new PDO($dsn, $this->user, $this->pass, $options);
            $this->logger->info("データベースに正常に接続しました。"); // 接続成功ログ
        } catch (PDOException $e) {
            $errorMsg = "データベース接続エラー: " . $e->getMessage();
            $this->logger->error($errorMsg); // 接続失敗ログ
            // 接続失敗は致命的なので、例外を再スローしてアプリケーションを停止させる
            throw new Exception($errorMsg, (int)$e->getCode(), $e);
        }
    }

    public function getConnection()
    {
        return $this->pdo; // 確立されたPDO接続を返す
    }
}
