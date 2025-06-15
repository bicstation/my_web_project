<?php

namespace App\Core;

class Logger
{
    private $logFile;

    public function __construct(string $logFile)
    {
        // Construct the log file path relative to the project root
        // From app/Core/Logger.php to project_root/logs/
        $this->logFile = __DIR__ . '/../../logs/' . $logFile;

        // Create the log directory if it doesn't exist
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0777, true)) {
                // Handle error if directory creation fails
                // For example, throw an exception or log to PHP's error log
                error_log("Failed to create log directory: " . $logDir);
            }
        }
    }

    /**
     * Writes a log message to the log file.
     *
     * @param string $level The log level (e.g., INFO, WARNING, ERROR).
     * @param string $message The log message.
     * @return void
     */
    private function writeLog(string $level, string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        // Append the log entry to the log file
        file_put_contents($this->logFile, $logEntry, FILE_APPEND);
    }

    /**
     * Logs an informational message.
     *
     * @param string $message The message to log.
     * @return void
     */
    public function info(string $message): void
    {
        $this->writeLog('INFO', $message);
    }

    /**
     * Logs a warning message.
     * This method was likely missing or incorrectly defined, causing the VS Code error.
     *
     * @param string $message The message to log.
     * @return void
     */
    public function warning(string $message): void
    {
        $this->writeLog('WARNING', $message);
    }

    /**
     * Logs an error message.
     *
     * @param string $message The message to log.
     * @return void
     */
    public function error(string $message): void
    {
        $this->writeLog('ERROR', $message);
    }

    /**
     * Logs an error message using a static context.
     * This can be used if an instance of Logger is not available.
     *
     * @param string $message The message to log.
     * @param string $logFile The name of the log file (e.g., 'application.log').
     * @return void
     */
    public static function staticError(string $message, string $logFile = 'application.log'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [ERROR] {$message}" . PHP_EOL;
        $filePath = __DIR__ . '/../../logs/' . $logFile;
        $logDir = dirname($filePath);
        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0777, true)) {
                error_log("Failed to create static log directory: " . $logDir);
            }
        }
        file_put_contents($filePath, $logEntry, FILE_APPEND);
    }
}
