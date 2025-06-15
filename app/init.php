<?php
// C:\project\my_web_project\app\init.php

// Composerのオートローダーを読み込む
// これにより、App名前空間下のクラスや、vlucas/phpdotenvなどのComposerが管理するライブラリが自動的にロードされます。
// init.php から見て vendor/autoload.php へのパスは '../../vendor/autoload.php' となります。
require_once __DIR__ . '/../vendor/autoload.php';

// App\Core\Session クラスをインポート
use App\Core\Session;

// セッションを開始
// Session::start() はセッションが既に開始されているかを確認し、まだであれば session_start() を呼び出します。
Session::start();

// セッションの活動状態をチェック（オプション：タイムアウト処理など）
Session::checkActivity();

// ここにその他のアプリケーション全体の初期化ロジックを追加できます。
// 例: エラーハンドリング設定、定数定義など。

// 例: デバッグ用のセッションID出力
// error_log("Session ID in init.php: " . session_id());

// CSRFトークンをセッションに設定（まだ存在しない場合）
if (!Session::has('csrf_token')) {
    Session::set('csrf_token', bin2hex(random_bytes(32)));
}
