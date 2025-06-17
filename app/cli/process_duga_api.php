<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "--- スクリプト実行開始（強制エラー表示ON） ---\n";

// ここで意図的に停止させる
die("テスト終了。\n"); 
?>