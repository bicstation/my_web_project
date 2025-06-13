<?php
// C:\doc\my_web_project\app\public\session_handler.php
// データベースを使ったカスタムセッションハンドラ

// 以下の警告が発生する場合の注意点:
// "ini_set(): Session ini settings cannot be changed after headers have already been sent"
// "session_set_save_handler(): Session save handler cannot be changed after headers have already been sent"
// "session_start(): Session cannot be started after headers have already been sent"
// これらの警告は、PHPがヘッダーを送信（つまり、何らかの出力がブラウザに送られた後）した後に、
// セッション関連の設定変更やセッション開始を行おうとすると発生します。
//
// 主な原因として考えられるのは：
// 1. このファイル (session_handler.php) の <?php タグの前に、余分な空白、改行、またはBOM (Byte Order Mark) がある。
// 2. require_once で読み込まれるファイル (特に vendor/autoload.php や init.php) に、同様の不要な出力がある。
// 3. アプリケーションコード内で echo や print などで明示的な出力が行われる前に、セッション開始処理が呼ばれていない。
// これらの問題を解決するには、すべてのPHPファイルの先頭が <?php で始まり、その前に何も文字がないことを確認してください。
// 特に、ライブラリファイルには終了タグを書かないのがPHPのベストプラクティスです。

// use PDO; // PDOクラスはPHPのグローバルクラスであるため、このuseステートメントは不要です。
// use PDOException; // PDOExceptionクラスはPHPのグローバルクラスであるため、このuseステートメントは不要です。

// SessionHandlerInterface を実装するクラスを定義
class DBSessionHandler implements SessionHandlerInterface {
    private ?PDO $db_conn = null; // データベース接続を保持するプロパティ

    /**
     * セッションを開くときに呼び出されます。
     * データベースに接続し、セッションハンドラで使用できるようにします。
     * @param string $savePath セッションデータの保存パス (この実装では無視)
     * @param string $sessionName セッション名 (この実装では無視)
     * @return bool 成功した場合は true、失敗した場合は false
     */
    #[\ReturnTypeWillChange] // Deprecated警告を抑制
    public function open($savePath, $sessionName) {
        // 環境変数からデータベース接続情報を取得
        $db_host = getenv('DB_HOST');
        $db_name = getenv('DB_NAME');
        $db_user = getenv('DB_USER');
        $db_password = getenv('DB_PASSWORD');

        // PDOを使ってデータベースに接続
        try {
            $this->db_conn = new PDO(
                "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
                $db_user,
                $db_password,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // エラーモードを例外に設定
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,     // デフォルトのフェッチモードを連想配列に設定
                    PDO::ATTR_EMULATE_PREPARES   => false,                // プリペアドステートメントのエミュレーションを無効化
                ]
            );
            error_log("DBSessionHandler::open - Database connection successful.");
            return true;
        } catch (PDOException $e) {
            error_log("DBSessionHandler::open - Database connection error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * セッションを閉じるときに呼び出されます。
     * データベース接続を閉じます。
     * @return bool 成功した場合は true、失敗した場合は false
     */
    #[\ReturnTypeWillChange] // Deprecated警告を抑制
    public function close() {
        error_log("DBSessionHandler::close - Closing session.");
        $this->db_conn = null; // PDO接続を閉じる
        return true;
    }

    /**
     * セッションデータを読み取るときに呼び出されます。
     * 指定されたセッションIDのデータをデータベースから取得します。
     * @param string $sessionId 読み取るセッションのID
     * @return string 読み取られたセッションデータ、または空文字列
     */
    #[\ReturnTypeWillChange] // Deprecated警告を抑制
    public function read($sessionId) {
        error_log("DBSessionHandler::read - Attempting to read session ID: " . $sessionId);
        // db_connがnullでないことを確認
        if ($this->db_conn === null) {
            error_log("DBSessionHandler::read - Database connection is null, cannot read session.");
            return '';
        }

        $stmt = $this->db_conn->prepare("SELECT data, expires_at FROM sessions WHERE session_id = ? AND expires_at > ?");
        $currentTime = time();
        
        try {
            $exec_result = $stmt->execute([$sessionId, $currentTime]);
            if (!$exec_result) {
                error_log("DBSessionHandler::read - Execute failed: " . json_encode($stmt->errorInfo()));
                return '';
            }

            $row = $stmt->fetch();
            if ($row) {
                error_log("DBSessionHandler::read - Data found for ID: " . $sessionId . ", expires_at: " . $row['expires_at'] . ", data_length: " . strlen($row['data']));
                // error_log("DBSessionHandler::read - Raw data fetched: " . $row['data']); // 大量のログになる可能性があるのでコメントアウト
                // PHPのセッションモジュールがこの返り値を非シリアライズ化するので、ここではunserializeしない
                return (string)$row['data'];
            } else {
                error_log("DBSessionHandler::read - No data found or expired for ID: " . $sessionId);
                return '';
            }
        } catch (PDOException $e) {
            error_log("DBSessionHandler::read - PDOException: " . $e->getMessage());
            return '';
        }
    }

    /**
     * セッションデータを書き込むときに呼び出されます。
     * セッションIDに紐付くデータをデータベースに保存または更新します。
     * @param string $sessionId 書き込むセッションのID
     * @param string $data 書き込むセッションデータ (シリアライズされたもの)
     * @return bool 成功した場合は true、失敗した場合は false
     */
    #[\ReturnTypeWillChange] // Deprecated警告を抑制
    public function write($sessionId, $data) {
        // db_connがnullでないことを確認
        if ($this->db_conn === null) {
            error_log("DBSessionHandler::write - Database connection is null, cannot write session.");
            return false;
        }

        $expires = time() + (int)ini_get('session.gc_maxlifetime');
        error_log("DBSessionHandler::write - Attempting to write session ID: " . $sessionId . ", data_length: " . strlen($data) . ", expires: " . $expires);
        // error_log("DBSessionHandler::write - Raw data received: " . $data); // 大量のログになる可能性があるのでコメントアウト

        $stmt = $this->db_conn->prepare("INSERT INTO sessions (session_id, data, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE data = ?, expires_at = ?");
        
        try {
            $exec_result = $stmt->execute([$sessionId, $data, $expires, $data, $expires]);
            if (!$exec_result) {
                error_log("DBSessionHandler::write - Execute failed: " . json_encode($stmt->errorInfo()));
            } else {
                error_log("DBSessionHandler::write - Write successful for ID: " . $sessionId);
            }
            return $exec_result;
        } catch (PDOException $e) {
            error_log("DBSessionHandler::write - PDOException: " . $e->getMessage());
            return false;
        }
    }

    /**
     * セッションを破棄するときに呼び出されます。
     * 指定されたセッションIDのデータをデータベースから削除します。
     * @param string $sessionId 破棄するセッションのID
     * @return bool 成功した場合は true、失敗した場合は false
     */
    #[\ReturnTypeWillChange] // Deprecated警告を抑制
    public function destroy($sessionId) {
        // db_connがnullでないことを確認
        if ($this->db_conn === null) {
            error_log("DBSessionHandler::destroy - Database connection is null, cannot destroy session.");
            return false;
        }

        error_log("DBSessionHandler::destroy - Attempting to destroy session ID: " . $sessionId);
        $stmt = $this->db_conn->prepare("DELETE FROM sessions WHERE session_id = ?");
        
        try {
            $exec_result = $stmt->execute([$sessionId]);
            if (!$exec_result) {
                error_log("DBSessionHandler::destroy - Execute failed: " . json_encode($stmt->errorInfo()));
            } else {
                error_log("DBSessionHandler::destroy - Destroy successful for ID: " . $sessionId);
            }
            return $exec_result;
        } catch (PDOException $e) {
            error_log("DBSessionHandler::destroy - PDOException: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 古いセッションデータをクリーンアップするときに呼び出されます。
     * 有効期限切れのセッションデータをデータベースから削除します。
     * @param int $maxlifetime セッションの最大有効期限 (秒)
     * @return bool 成功した場合は true、失敗した場合は false
     */
    #[\ReturnTypeWillChange] // Deprecated警告を抑制
    public function gc($maxlifetime) {
        // db_connがnullでないことを確認
        if ($this->db_conn === null) {
            error_log("DBSessionHandler::gc - Database connection is null, cannot run garbage collection.");
            return false;
        }
        
        $pastTime = time();
        error_log("DBSessionHandler::gc - Running garbage collection, maxlifetime: " . $maxlifetime . ", removing before: " . $pastTime);
        $stmt = $this->db_conn->prepare("DELETE FROM sessions WHERE expires_at < ?");
        
        try {
            $exec_result = $stmt->execute([$pastTime]);
            if (!$exec_result) {
                error_log("DBSessionHandler::gc - Execute failed: " . json_encode($stmt->errorInfo()));
            } else {
                error_log("DBSessionHandler::gc - Garbage collection successful. Rows affected: " . $stmt->rowCount());
            }
            return $exec_result;
        } catch (PDOException $e) {
            error_log("DBSessionHandler::gc - PDOException: " . $e->getMessage());
            return false;
        }
    }
}
