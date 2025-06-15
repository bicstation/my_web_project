<?php
// C:\project\my_web_project\app\init.php

// エラーレポート設定 (開発中はこれらを有効にするのがベスト)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// セッションを開始
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

// Composerのオートローダーはindex.phpで読み込まれることを想定。
// init.phpを直接実行するケースがあるならここにも必要。
// require_once __DIR__ . '/../../vendor/autoload.php';

// ここではクラス定義は行いません。
// クラスはそれぞれ独立したファイル (App/Core/Session.php, App/Core/Logger.php, App/Core/Database.php)
// に定義されており、Composerのオートロードによって読み込まれます。

// ユーザーの活動時間を更新し、一定期間操作がない場合は自動的にログアウトさせる
// (これは Session クラスが既にロードされている index.php で行うべき処理です)
// または、init.php が実行される時点で App\Core\Session クラスがオートロードされるように設定されている場合
// (Composer の psr-4 設定が適切なら可能) にのみここで実行できます。
// 現在の構成では index.php で Session クラスを use してから実行するのが安全です。

?>
