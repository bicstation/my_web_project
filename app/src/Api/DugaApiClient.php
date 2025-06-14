<?php
// C:\project\my_web_project\app\src\Api\DugaApiClient.php
namespace App\Api;

use App\Core\Logger; // 新しいロギングクラスを使用
use Exception;      // 例外処理用

class DugaApiClient
{
    private Logger $logger;
    private string $apiUrl;
    private string $apiKey; // Duga APIキー

    /**
     * DugaApiClient constructor.
     * @param string $apiUrl Duga APIのエンドポイントURL
     * @param string $apiKey Duga APIのAPIキー
     * @param Logger $logger ロギングのためのLoggerインスタンス
     */
    public function __construct(string $apiUrl, string $apiKey, Logger $logger)
    {
        $this->apiUrl = $apiUrl;
        $this->apiKey = $apiKey;
        $this->logger = $logger;
        $this->logger->log("DugaApiClient initialized with API URL: {$this->apiUrl}");
    }

    /**
     * Duga APIからアイテムデータを取得します。
     * @param int $offset 取得開始オフセット (Duga APIは1から始まる)
     * @param int $hits 一度に取得するアイテム数
     * @param array $additionalParams APIリクエストに追加する追加パラメータ
     * @return array 取得したアイテムデータの配列、またはエラー時に空の配列
     */
    public function getItems(int $offset, int $hits, array $additionalParams = []): array
    {
        // APIキーが設定されているか確認
        if (empty($this->apiKey) || $this->apiKey === 'YOUR_DUGA_API_KEY_HERE') {
            $this->logger->error("Duga API Key is not set or is invalid. Please check your your .env file.");
            return []; // キーがない場合は処理を中断
        }

        // Duga APIの正しいパラメータ名と必須パラメータを追加
        $queryParams = array_merge([
            'offset' => $offset,
            'hits'   => $hits,
            'version' => '1.2',  // ★追加: 必須のversionパラメータ
            'appid' => $this->apiKey, // ★修正: apikeyをappidに変更
            'format' => 'json' // ★追加: 必須のformatパラメータをjsonに設定
        ], $additionalParams);

        // API URLを正しい形式に修正
        // Duga APIの検索エンドポイントは通常 "search" です
        // そして、クエリパラメータを直接後ろに付けます
        $url = $this->apiUrl . '?' . http_build_query($queryParams); // ★修正: '/item' を削除し、URL構造を変更
        $this->logger->log("Requesting Duga API URL: " . $url);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Timeout in seconds
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $this->logger->error("cURL error when fetching Duga API: " . $error);
            return [];
        }

        if ($httpCode !== 200) {
            $this->logger->error("Duga API request failed with HTTP code {$httpCode}. Response: {$response}");
            return [];
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error("JSON decode error from Duga API: " . json_last_error_msg() . ". Response: " . $response);
            return [];
        }
        
        // Duga APIのレスポンス構造に応じて、必要なデータを抽出
        // 正しいAPIのレスポンスは 'result' キーの下に商品データを持つことが多いと仮定
        $items = $data['result'] ?? []; // ★修正: 'items' ではなく 'result' を優先
        if (empty($items)) {
            $this->logger->log("Duga API returned empty 'result' array or 'result' key not found.");
            // 旧APIの 'items' も念のため確認（もし混合する可能性があれば）
            if (isset($data['items']) && is_array($data['items'])) {
                $items = $data['items'];
                $this->logger->log("Fallback: Duga API returned 'items' array.");
            }
        } else {
            $this->logger->log("Duga API returned " . count($items) . " items.");
        }
        return $items; 
    }
}
