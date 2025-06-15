<?php

namespace App\Core;

/**
 * Session クラス
 * セッションの開始、設定、データの操作、ログイン/ログアウト管理を行います。
 */
class Session
{
    /**
     * セッションを開始します。
     * 既にセッションが開始されている場合は何もしません。
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * セッションにデータをセットします。
     *
     * @param string $key キー
     * @param mixed $value 値
     */
    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * セッションからデータを取得します。
     *
     * @param string $key キー
     * @param mixed $default デフォルト値 (キーが存在しない場合)
     * @return mixed セッションの値、またはデフォルト値
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * セッションからデータを削除します。
     *
     * @param string $key キー
     */
    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * 指定されたキーのセッションデータが存在するかチェックします。
     *
     * @param string $key キー
     * @return bool 存在すれば true、そうでなければ false
     */
    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * 現在のセッションIDを再生成し、古いセッションファイルを削除します。
     * セキュリティ向上のため、ログイン時などに呼び出すことを推奨します。
     *
     * @param bool $delete_old_session 古いセッションファイルを削除するかどうか (デフォルト: true)
     */
    public static function regenerateId(bool $delete_old_session = true): void
    {
        session_regenerate_id($delete_old_session);
    }

    /**
     * ユーザーをログイン状態にします。
     * ユーザーIDやロールなどの情報をセッションに保存し、セッションIDを再生成します。
     *
     * @param int $userId ユーザーID
     * @param string $username ユーザー名
     * @param string $role ユーザーの役割 (例: 'user', 'admin')
     */
    public static function login(int $userId, string $username, string $role): void
    {
        self::start(); // セッションが開始されていない場合は開始
        self::regenerateId(); // セッション固定攻撃対策

        self::set('user_id', $userId);
        self::set('username', $username);
        self::set('role', $role);
        self::set('logged_in', true);
        self::set('last_activity', time()); // 最終活動時刻
    }

    /**
     * ユーザーがログインしているかチェックします。
     *
     * @return bool ログインしていれば true、そうでなければ false
     */
    public static function isLoggedIn(): bool
    {
        self::start(); // セッションが開始されていない場合は開始
        return self::has('logged_in') && self::get('logged_in') === true;
    }

    /**
     * ログインしているユーザーのIDを取得します。
     *
     * @return int|null ユーザーID、またはログインしていなければ null
     */
    public static function getUserId(): ?int
    {
        return self::get('user_id');
    }

    /**
     * ログインしているユーザーの役割を取得します。
     *
     * @return string|null ユーザーの役割、またはログインしていなければ null
     */
    public static function getUserRole(): ?string
    {
        return self::get('role');
    }

    /**
     * ユーザーをログアウトさせます。
     * セッションデータをすべてクリアし、セッションを破棄します。
     */
    public static function logout(): void
    {
        self::start(); // セッションが開始されていない場合は開始
        $_SESSION = []; // 全てのセッションデータをクリア
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy(); // セッションファイルを破棄
    }

    /**
     * セッションハイジャック対策のための最終活動時刻チェック。
     * 一定時間操作がない場合、セッションを期限切れと見なす。
     * 必要に応じてログインページにリダイレクトするなどの処理を追加。
     *
     * @param int $timeout_seconds タイムアウトまでの秒数 (デフォルト: 1800秒 = 30分)
     * @return bool セッションが有効であれば true、無効であれば false (ログアウト済みの場合)
     */
    public static function checkActivity(int $timeout_seconds = 1800): bool
    {
        self::start();
        $lastActivity = self::get('last_activity');

        if ($lastActivity && (time() - $lastActivity > $timeout_seconds)) {
            self::logout(); // タイムアウトしたらログアウト
            // 必要に応じてリダイレクト処理などをここに追加
            return false;
        }

        self::set('last_activity', time()); // 活動を更新
        return true;
    }
}
