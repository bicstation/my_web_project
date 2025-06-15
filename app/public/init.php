<?php
// C:\project\my_web_project\app\init.php

// エラーレポート設定 (開発中はこれらを有効にするのがベスト)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// セッションを開始 (これは常に最上部で行うべき処理の一つ)
// session_start() の前に何らかの出力があった場合、'headers already sent' エラーが発生します。
// そのため、init.php 自体の先頭にも空白やBOMがないことを確認してください。
if (session_status() == PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 3600, // 1時間
        'path' => '/',
        'domain' => '', // 必要に応じてドメインを指定 (例: '.yourdomain.com')
        'secure' => true, // HTTPSでのみクッキーを送信
        'httponly' => true, // JavaScriptからのアクセスを禁止
        'samesite' => 'Lax' // CSRF対策
    ]);
    session_name('MYAPPSESSID'); // セッションクッキー名を指定
    session_start();
}

// Sessionクラスの定義 (例として含めます。実際には App/Core/Session.php にあるはずです)
// ユーザーが提供していないため、一般的な実装を仮定します。
if (!class_exists('App\Core\Session')) {
    namespace App\Core;

    class Session
    {
        public static function init()
        {
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
        }

        public static function set($key, $value)
        {
            $_SESSION[$key] = $value;
        }

        public static function get($key)
        {
            return $_SESSION[$key] ?? null;
        }

        public static function has($key)
        {
            return isset($_SESSION[$key]);
        }

        public static function remove($key)
        {
            if (isset($_SESSION[$key])) {
                unset($_SESSION[$key]);
            }
        }

        public static function destroy()
        {
            session_unset();
            session_destroy();
            $_SESSION = [];
        }

        // ユーザーログイン時にセッションを設定する
        public static function login($userId, $username, $userRole)
        {
            session_regenerate_id(true); // セッション固定攻撃対策
            self::set('user_id', $userId);
            self::set('user_username', $username); // ユーザー名を 'user_username' として保存
            self::set('user_role', $userRole);
            self::set('last_activity', time());
        }

        // ログイン状態をチェックする
        public static function isLoggedIn()
        {
            return self::has('user_id');
        }

        // ログアウト処理
        public static function logout()
        {
            self::destroy();
        }

        // ユーザーIDを取得
        public static function getUserId()
        {
            return self::get('user_id');
        }

        // ユーザー名をセッションから取得
        public static function getUsername()
        {
            return self::get('user_username'); // 'user_username' を取得
        }

        // ユーザーの役割を取得
        public static function getUserRole()
        {
            return self::get('user_role');
        }

        // CSRFトークンを生成し、セッションに保存する
        public static function generateCsrfToken()
        {
            if (!self::has('csrf_token')) {
                self::set('csrf_token', bin2hex(random_bytes(32)));
            }
            return self::get('csrf_token');
        }

        // CSRFトークンを検証する
        public static function verifyCsrfToken($token)
        {
            $sessionToken = self::get('csrf_token');
            // トークンが一致し、かつ一度使用されたら新しいトークンを生成
            if ($token && hash_equals($sessionToken, $token)) { // hash_equalsでタイミング攻撃を防止
                // セキュリティ向上のため、使用済みトークンはすぐに無効化し新しいものを生成
                self::remove('csrf_token');
                self::set('csrf_token', bin2hex(random_bytes(32))); // 新しいトークンを生成
                return true;
            }
            return false;
        }
    }
}

use App\Core\Session; // Sessionクラスをインポート

// CSRFトークンを常に生成 (ログインページ以外でも必要に応じて)
// 特にログインフォームを表示する前に確実にトークンが設定されているように
if (!Session::has('csrf_token')) {
    Session::generateCsrfToken();
}

// ユーザーの活動時間を更新し、一定期間操作がない場合は自動的にログアウトさせる
// (オプション)
if (Session::isLoggedIn()) {
    $lastActivity = Session::get('last_activity');
    $inactiveTime = 1800; // 30分

    if (time() - $lastActivity > $inactiveTime) {
        Session::logout();
        header('Location: index.php?page=login&timeout=1');
        exit();
    }
    Session::set('last_activity', time()); // アクティビティを更新
}

// Loggerクラスの定義 (例として含めます。実際には App/Core/Logger.php にあるはずです)
if (!class_exists('App\Core\Logger')) {
    namespace App\Core;

    class Logger
    {
        private $logFile;

        public function __construct($filename = 'app.log')
        {
            $this->logFile = __DIR__ . '/../../logs/' . $filename;
            // ログディレクトリが存在しない場合は作成
            if (!is_dir(dirname($this->logFile))) {
                mkdir(dirname($this->logFile), 0777, true);
            }
        }

        private function writeLog($level, $message)
        {
            $timestamp = date('Y-m-d H:i:s');
            $logEntry = sprintf("[%s] [%s] %s\n", $timestamp, strtoupper($level), $message);
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
}

// Databaseクラスの定義 (例として含めます。実際には App/Core/Database.php にあるはずです)
if (!class_exists('App\Core\Database')) {
    namespace App\Core;

    use PDO;
    use PDOException;

    class Database
    {
        private $host;
        private $dbname;
        private $user;
        private $pass;
        private $charset;
        private $pdo;
        private $logger;

        public function __construct(array $config, Logger $logger)
        {
            $this->host = $config['host'];
            $this->dbname = $config['dbname'];
            $this->user = $config['user'];
            $this->pass = $config['pass'];
            $this->charset = $config['charset'];
            $this->logger = $logger;

            $this->connect();
        }

        private function connect()
        {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                $this->pdo = new PDO($dsn, $this->user, $this->pass, $options);
                $this->logger->info("データベースに正常に接続しました。");
            } catch (PDOException $e) {
                $errorMsg = "データベース接続エラー: " . $e->getMessage();
                $this->logger->error($errorMsg);
                throw new Exception($errorMsg, (int)$e->getCode(), $e);
            }
        }

        public function getConnection()
        {
            return $this->pdo;
        }
    }
}
?>
