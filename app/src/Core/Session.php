<?php
// C:\project\my_web_project\app\src\Core\Session.php

namespace App\Core;

class Session
{
    /**
     * セッションを開始または再開します。
     * 既にセッションが開始されている場合は何もしません。
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * 指定されたキーでセッションに値を設定します。
     *
     * @param string $key キー
     * @param mixed $value 値
     */
    public static function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * 指定されたキーからセッションの値を取得します。
     * キーが存在しない場合は、オプションでデフォルト値を返します。
     *
     * @param string $key キー
     * @param mixed $default デフォルト値 (オプション)
     * @return mixed セッションの値またはデフォルト値
     */
    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * 指定されたキーのセッション値を削除します。
     *
     * @param string $key キー
     */
    public static function remove(string $key): void
    {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }

    /**
     * セッションから指定されたキーを削除します。
     *
     * @param string $key 削除するセッションキー
     * @return void
     */
    public static function delete(string $key): void
    {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }

    // ... その他の既存メソッド (get, set, has, login, checkActivity, isLoggedIn, logout など) ...


    /**
     * セッションに指定されたキーが存在するかどうかを確認します。
     *
     * @param string $key キー
     * @return bool キーが存在すれば true、そうでなければ false
     */
    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * 現在のセッションを破棄し、すべてのセッションデータをクリアします。
     */
    public static function destroy(): void
    {
        session_unset();   // すべてのセッション変数を解除
        session_destroy(); // セッションを破棄
        // クッキーからセッションIDを削除（オプション）
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
    }

    /**
     * ユーザーがログインしているかどうかをチェックします。
     *
     * @return bool ログインしていれば true、そうでなければ false
     */
    public static function isLoggedIn(): bool
    {
        return self::has('user_id');
    }

    /**
     * ユーザーをログインさせます。
     *
     * @param int $userId ユーザーID
     * @param string $userRole ユーザーのロール (例: 'user', 'admin')
     */
    public static function login(int $userId, string $userRole): void
    {
        self::set('user_id', $userId);
        self::set('user_role', $userRole);
        self::set('last_activity', time()); // 最終活動時刻を記録
    }

    /**
     * ユーザーをログアウトさせます。
     */
    public static function logout(): void
    {
        self::destroy();
    }

    /**
     * 現在ログインしているユーザーのIDを取得します。
     *
     * @return int|null ユーザーID、またはログインしていない場合は null
     */
    public static function getUserId(): ?int
    {
        return self::get('user_id');
    }

    /**
     * 現在ログインしているユーザーのロールを取得します。
     *
     * @return string|null ユーザーのロール、またはログインしていない場合は null
     */
    public static function getUserRole(): ?string
    {
        return self::get('user_role');
    }

    /**
     * セッションの活動状態をチェックし、非活動状態が一定時間続けばログアウトさせます。
     * このメソッドは、各リクエストの開始時に呼び出すことを想定しています。
     * 必要に応じて、タイムアウト時間 (秒) を調整してください。
     */
    public static function checkActivity(): void
    {
        // 1時間がタイムアウト時間 (秒)
        $timeout = 3600;

        if (self::has('last_activity') && (time() - self::get('last_activity') > $timeout)) {
            // タイムアウトした場合
            self::logout();
            // フラッシュメッセージは、ログインページなどで表示されるように設定することが一般的です
            // self::set('flash_message', 'セッションがタイムアウトしました。再度ログインしてください。');
        } else {
            // アクティブな場合は最終活動時刻を更新
            self::set('last_activity', time());
        }
    }
}
