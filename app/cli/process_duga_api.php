<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "--- スクリプト実行開始（強制エラー表示ON） ---\n";

try {
    // Composerのオートローダーを読み込む
    require_once __DIR__ . '/../../vendor/autoload.php';
    echo "--- オートローダー読み込み成功 ---\n";
} catch (Throwable $e) { // Error も含む Exception をキャッチ
    echo "--- オートローダー読み込み失敗: " . $e->getMessage() . "\n";
    error_log("オートローダー読み込み失敗 (エラーログ): " . $e->getMessage());
}

die("テスト終了。\n");
?>