<?php
// C:\project\my_web_project\app\src\Core\Logger.php
namespace App\Core;

use DateTime; // PHPの組み込みDateTimeクラスを使用するためuse宣言

class Logger
{
    private string $logFile;
    private string $dateFormat = 'Y-m-d H:i:s';

    /**
     * Logger constructor.
     * @param string $filename ログファイルの名前 (例: 'duga_api_processing.log')
     * ログファイルは 'storage/logs/' ディレクトリ直下に作成されます。
     */
    public function __construct(string $filename)
    {
        // ログファイルのベースディレクトリを設定
        // __DIR__ は現在のファイル (Logger.php) のディレクトリ (app/src/Core) を指します。
        // '/../../../' でプロジェクトルート (my_web_project/) に戻り、
        // その後 'app/storage/logs/' を指定してログディレクトリを構築します。
        // 例: C:/project/my_web_project/app/storage/logs/
        $baseLogDirectory = realpath(__DIR__ . '/../../../app/storage/logs');

        // ベースログディレクトリが存在しない、または取得できない場合はエラーを投げる
        if ($baseLogDirectory === false) {
            throw new \RuntimeException("Could not resolve base log directory. Check path: " . __DIR__ . '/../../../app/storage/logs');
        }

        // ログファイルのディレクトリパスを構築
        $logDir = $baseLogDirectory;

        // ログファイルのディレクトリが存在しない場合は作成
        // mkdirの第3引数(true)で再帰的にディレクトリを作成します
        // 0777は読み書き実行の全ての権限を与えます。本番環境ではより厳しく設定することを検討してください。
        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0777, true)) {
                // ディレクトリ作成に失敗した場合のエラーハンドリング
                throw new \RuntimeException("Failed to create log directory: {$logDir}");
            }
        }

        // CORRECTED LINE: `$this->logFile` に正しいパスを代入
        // ログファイル名とディレクトリを結合してフルパスを設定します。
        $this->logFile = $logDir . DIRECTORY_SEPARATOR . $filename;
    }

    /**
     * 情報メッセージをログに記録します。
     * @param string $message ログメッセージ
     */
    public function log(string $message): void
    {
        $this->writeLog('INFO', $message);
    }

    /**
     * エラーメッセージをログに記録します。
     * @param string $message ログメッセージ
     */
    public function error(string $message): void
    {
        $this->writeLog('ERROR', $message);
    }

    /**
     * ログファイルにメッセージを書き込みます。
     *
     * @param string $level ログレベル (INFO, ERRORなど)
     * @param string $message ログメッセージ
     * @throws \RuntimeException ログファイルへの書き込みに失敗した場合
     */
    private function writeLog(string $level, string $message): void
    {
        // ログファイルが書き込み可能かチェック（新規作成または既存ファイルへの追記）
        // file_exists($this->logFile) はファイルが存在するか、!is_writable(dirname($this->logFile)) はディレクトリが書き込み可能かを確認
        // この両方でログへの書き込み権限を確認しています
        if ((file_exists($this->logFile) && !is_writable($this->logFile)) || (!file_exists($this->logFile) && !is_writable(dirname($this->logFile)))) {
            error_log("Log file or directory is not writable: " . $this->logFile);
            throw new \RuntimeException("Log file or directory is not writable.");
        }

        $dateTime = new DateTime();
        $logEntry = "[" . $dateTime->format($this->dateFormat) . "] [{$level}] {$message}\n";

        // ファイルにメッセージを追記します。
        // FILE_APPEND: ファイルの末尾にデータを追加します。
        // LOCK_EX: ファイルをロックし、他の書き込み処理との競合を防ぎます。
        if (file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX) === false) {
            // 書き込み失敗時のエラーハンドリング
            throw new \RuntimeException("Failed to write to log file: " . $this->logFile);
        }
    }
}
