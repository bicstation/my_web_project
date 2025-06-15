<?php
// C:\project\my_web_project\app\Core\Session.php

namespace App\Core;

// セッション関連のヘルパー関数や状態管理を行うクラス
class Session
{
    public static function init()
    {
        // session_start() は init.php で既に呼び出されていることを想定
        // 必要であればここで再度チェックすることも可能だが、通常は不要
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
        session_unset(); // セッション変数を全て解除
        session_destroy(); // セッションデータを破棄
        $_SESSION = []; // $_SESSION変数を空にする
    }

    // ユーザーログイン時にセッションを設定する
    public static function login($userId, $username, $userRole)
    {
        session_regenerate_id(true); // セッション固定攻撃対策のためにIDを再生成
        self::set('user_id', $userId);
        self::set('user_username', $username); // ユーザー名を 'user_username' として保存
        self::set('user_role', $userRole);
        self::set('last_activity', time()); // 最終活動時刻を記録
    }

    // ログイン状態をチェックする
    public static function isLoggedIn()
    {
        return self::has('user_id'); // 'user_id' が存在すればログイン中と判断
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
        return self::get('user_username');
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
            self::set('csrf_token', bin2hex(random_bytes(32))); // 32バイトのランダムなバイトをHEX文字列に変換
        }
        return self::get('csrf_token');
    }

    // CSRFトークンを検証する
    public static function verifyCsrfToken($token)
    {
        $sessionToken = self::get('csrf_token');
        // hash_equals はタイミング攻撃を防ぐ安全な比較関数
        if ($token && hash_equals($sessionToken, $token)) {
            // セキュリティ向上のため、使用済みトークンはすぐに無効化し新しいものを生成
            self::remove('csrf_token');
            self::set('csrf_token', bin2hex(random_bytes(32))); // 新しいトークンを生成
            return true;
        }
        return false;
    }
}
