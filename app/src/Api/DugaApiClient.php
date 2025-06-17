<?php
// C:\project\my_web_project\app\src\Api\DugaApiClient.php
namespace App\Api;

use App\Core\Logger;
use Exception;

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
     * @return array 取得したアイテムの配列と総件数。例: ['items' => [...], 'count' => 123]
     * @throws Exception API呼び出しに失敗した場合
     */
    public function getItems(int $offset, int $hits, array $additionalParams = []): array
    {
        // APIキーが設定されているか確認
        if (empty($this->apiKey) || $this->apiKey === 'YOUR_DUGA_API_KEY_HERE') {
            $this->logger->error("Duga API Key is not set or is invalid. Please check your .env file.");
            throw new Exception("Duga API Key is not set or is invalid.");
        }

        $queryParams = array_merge([
            'offset' => $offset,
            'hits'   => $hits,
            'version' => '1.2',
            'appid' => $this->apiKey, // APIキーは 'appid' で渡す
            'format' => 'json'
        ], $additionalParams);

        // Duga APIの検索エンドポイントは通常、ベースURLに直接パラメータを付けます
        $url = $this->apiUrl . '?' . http_build_query($queryParams);
        $this->logger->log("Requesting Duga API URL: " . $url);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // タイムアウト設定

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $this->logger->error("cURL error when fetching Duga API: " . $error);
            throw new Exception("cURL error when fetching Duga API: " . $error);
        }

        if ($httpCode !== 200) {
            $this->logger->error("Duga API request failed with HTTP code {$httpCode}. Response: {$response}");
            throw new Exception("Duga API request failed with HTTP code {$httpCode}");
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error("JSON decode error from Duga API: " . json_last_error_msg() . ". Response: " . $response);
            throw new Exception("JSON decode error from Duga API: " . json_last_error_msg());
        }
        
        // --- ここからDuga APIのレスポンス構造に合わせた修正 ---
        // Duga APIのレスポンスはルートに'items'と'count'を持つことを期待
        $items = $data['items'] ?? []; // 'items' キーが存在しない場合は空の配列
        $count = $data['count'] ?? 0;   // 'count' キーが存在しない場合は0

        $this->logger->log("Duga APIから取得したアイテム数: " . count($items) . ", 総件数: " . $count);

        // アイテムの配列と総件数を返す (total_hits を count に変更)
        return ['items' => $items, 'count' => $count];
    }
}