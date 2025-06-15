<?php
// C:\project\my_web_project\app\Core\Logger.php

namespace App\Core;

// アプリケーションのログ記録を行うクラス
class Logger
{
    private $logFile;

    public function __construct($filename = 'app.log')
    {
        $this->logFile = __DIR__ . '/../../logs/' . $filename; // logsディレクトリにログファイルを作成
        // ログディレクトリが存在しない場合は作成
        if (!is_dir(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0777, true); // 0777は開発用。本番ではより厳格なパーミッションを推奨
        }
    }

    private function writeLog($level, $message)
    {
        $timestamp = date('Y-m-d H:i:s'); // 現在のタイムスタンプ
        $logEntry = sprintf("[%s] [%s] %s\n", $timestamp, strtoupper($level), $message); // ログエントリのフォーマット
        // FILE_APPEND: ファイルの終わりに書き込む, LOCK_EX: 排他ロックで他のプロセスからの同時書き込みを防ぐ
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    public function info($message)
    {
        $this->writeLog('info', $message);
    }

    public function warning($message)
    {
        $this->writeLog('warning', $message);
    }

    public function error($message)
    {
        $this->writeLog('error', $message);
    }
}
