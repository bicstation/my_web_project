<?php
// app/src/Util/LinkClicker.php

namespace App\Util;

use App\Core\Database;
use App\Core\Logger;
use PDOException; // PDOExceptionをインポート

class LinkClicker
{
    private $database;
    private $logger;

    public function __construct(Database $database, Logger $logger)
    {
        $this->database = $database;
        $this->logger = $logger;
    }

    /**
     * クリックイベントをログに記録します。
     *
     * @param string $productId クリックされた商品の product_id (products.product_id)
     * @param string $clickType クリックの種類 (例: 'affiliate_link', 'detail_view', 'banner_click')
     * @param string|null $referrer 参照元URL
     * @param string|null $userAgent ユーザーエージェント文字列
     * @param string|null $ipAddress IPアドレス
     * @param string|null $redirectUrl リダイレクト先のURL（クリックリダイレクトの場合）または現在のページURL（詳細ビューの場合）
     * @return bool 成功した場合true、失敗した場合false
     */
    public function logClick(
        string $productId,
        string $clickType,
        ?string $referrer = null,
        ?string $userAgent = null,
        ?string $ipAddress = null,
        ?string $redirectUrl = null
    ): bool {
        try {
            $pdo = $this->database->getConnection();
            $stmt = $pdo->prepare(
                "INSERT INTO link_clicks (product_id, click_type, referrer, user_agent, ip_address, redirect_url, clicked_at)
                 VALUES (:product_id, :click_type, :referrer, :user_agent, :ip_address, :redirect_url, NOW())"
            );

            $stmt->bindParam(':product_id', $productId);
            $stmt->bindParam(':click_type', $clickType);
            $stmt->bindParam(':referrer', $referrer);
            $stmt->bindParam(':user_agent', $userAgent);
            $stmt->bindParam(':ip_address', $ipAddress);
            $stmt->bindParam(':redirect_url', $redirectUrl);

            $stmt->execute();
            $this->logger->log("クリックログ記録成功: ProductID={$productId}, Type={$clickType}");
            return true;

        } catch (PDOException $e) {
            $this->logger->error("クリックログ記録失敗: ProductID={$productId}, Type={$clickType}, エラー: " . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            // その他の予期せぬエラー
            $this->logger->error("クリックログ記録中に予期せぬエラー: ProductID={$productId}, Type={$clickType}, エラー: " . $e->getMessage());
            return false;
        }
    }
}