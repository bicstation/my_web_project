<?php
// app/src/Core/Logger.php

namespace App\Core;

class Logger
{
    private $logFile;
    private $logLevel;

    public function __construct(string $logFilePath, string $logLevel = 'info')
    {
        $this->logFile = $logFilePath;
        $this->logLevel = $logLevel;

        $logDir = dirname($this->logFile);

        // ログディレクトリが存在しない場合にのみ作成する
        if (!is_dir($logDir)) {
            // mkdir(パス, パーミッション, 再帰的に作成するかどうか);
            if (!mkdir($logDir, 0777, true)) { // true は再帰的作成を意味する
                // ディレクトリ作成に失敗した場合の致命的なエラー処理
                error_log("Failed to create log directory: " . $logDir);
                throw new \Exception("ログディレクトリを作成できません: " . $logDir);
            }
        }
    }

    public function log(string $message, string $level = 'info'): void
    {
        $this->writeLog($message, $level);
    }

    public function info(string $message): void
    {
        $this->writeLog($message, 'INFO');
    }

    public function warning(string $message): void
    {
        $this->writeLog($message, 'WARNING');
    }

    public function error(string $message): void
    {
        $this->writeLog($message, 'ERROR');
    }

    private function writeLog(string $message, string $level): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
        // FILE_APPEND は既存ファイルに追記。LOCK_EX は排他ロックをかけて書き込み中の競合を防ぐ
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
}