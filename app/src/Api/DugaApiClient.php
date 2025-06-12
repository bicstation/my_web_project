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
        $queryParams = array_merge([
            'offset' => $offset,
            'hits'   => $hits,
            'apikey' => $this->apiKey,
        ], $additionalParams);

        $url = $this->apiUrl . 'item?' . http_build_query($queryParams);
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
        // 一般的なDuga APIは 'items' または 'result' キーの下に商品データを持つことが多い
        // ここでは 'items' キーを想定していますが、APIドキュメントに合わせて調整してください
        return $data['items'] ?? []; 
    }
}
