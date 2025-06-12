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
     * ログファイルは 'app/' ディレクトリ直下に作成されます。
     */
    public function __construct(string $filename)
    {
        // ログファイルのパスを絶対パスで設定
        // __DIR__ は現在のファイル (Logger.php) のディレクトリ (app/src/Core) を指す
        // '/../../../' で MY_WEB_PROJECT/app/ ディレクトリに移動し、そこにログファイルを置く
        $this->logFile = __DIR__ . '/../../../app/' . $filename; 
        
        // ログファイルのディレクトリが存在しない場合は作成
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true); // 再帰的にディレクトリを作成
        }
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
     * @param string $level ログレベル (INFO, ERRORなど)
     * @param string $message ログメッセージ
     */
    private function writeLog(string $level, string $message): void
    {
        $dateTime = new DateTime();
        $logEntry = "[" . $dateTime->format($this->dateFormat) . "] [{$level}] {$message}\n";
        file_put_contents($this->logFile, $logEntry, FILE_APPEND);
    }
}
