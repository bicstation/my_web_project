<?php
// C:\project\my_web_project\app\src\Util\DbBatchInsert.php
namespace App\Util;

use App\Core\Database; // Databaseクラスを使用
use App\Core\Logger;   // Loggerクラスを使用
use PDO;
use PDOException;

class DbBatchInsert
{
    private PDO $pdo;
    private Logger $logger;

    /**
     * DbBatchInsert constructor.
     * @param Database $database Databaseインスタンス
     * @param Logger $logger ロギングのためのLoggerインスタンス
     */
    public function __construct(Database $database, Logger $logger)
    {
        $this->pdo = $database->getConnection();
        $this->logger = $logger;
        $this->logger->log("DbBatchInsert initialized.");
    }

    /**
     * 指定されたテーブルに複数のレコードをバルク挿入またはUPSERTします。
     * @param string $tableName 挿入対象のテーブル名
     * @param array $data 挿入するレコードの配列。各要素はキーがカラム名、値がデータとなる連想配列である必要があります。
     * 例: [['col1' => 'valA', 'col2' => 'valB'], ['col1' => 'valC', 'col2' => 'valD']]
     * @param array $updateColumns UPSERT時に更新するカラムの配列。空の場合は通常のINSERTになります。
     * @return int 挿入または更新された行の合計数
     * @throws PDOException データベース操作中にエラーが発生した場合
     */
    public function insertOrUpdate(string $tableName, array $data, array $updateColumns = []): int
    {
        if (empty($data)) {
            $this->logger->log("No data provided for batch insert into '{$tableName}'. Skipping.");
            return 0;
        }

        // 最初のデータ行からカラム名を取得
        $firstRow = $data[0];
        $columns = array_keys($firstRow);
        $columnNames = implode(', ', $columns);
        $placeholders = ':' . implode(', :', $columns);

        $sql = "INSERT INTO {$tableName} ({$columnNames}) VALUES ({$placeholders})";

        // UPSERT (ON DUPLICATE KEY UPDATE) ロジックを追加
        if (!empty($updateColumns)) {
            $updateParts = [];
            foreach ($updateColumns as $col) {
                $updateParts[] = "{$col} = VALUES({$col})";
            }
            $sql .= " ON DUPLICATE KEY UPDATE " . implode(', ', $updateParts);
            $this->logger->log("Performing UPSERT into '{$tableName}'. Update columns: " . implode(', ', $updateColumns));
        } else {
            $this->logger->log("Performing standard INSERT into '{$tableName}'.");
        }

        $stmt = $this->pdo->prepare($sql);
        $insertedOrUpdatedCount = 0;

        try {
            $this->pdo->beginTransaction(); // トランザクション開始
            foreach ($data as $row) {
                $stmt->execute($row);
                $insertedOrUpdatedCount += $stmt->rowCount(); // affected rows (for INSERT and UPDATE)
            }
            $this->pdo->commit(); // トランザクションコミット
            $this->logger->log("Successfully inserted/updated {$insertedOrUpdatedCount} rows into '{$tableName}'.");
        } catch (PDOException $e) {
            $this->pdo->rollBack(); // エラー時はロールバック
            $errorMessage = "Failed to batch insert/update into '{$tableName}': " . $e->getMessage();
            $this->logger->error($errorMessage);
            throw new PDOException($errorMessage, (int)$e->getCode()); // 例外を再スロー
        }

        return $insertedOrUpdatedCount;
    }

    /**
     * raw_api_dataテーブルに挿入されたレコードのIDを取得します。
     * これは products テーブルとの紐付けに使用されます。
     * @param string $sourceName APIソース名
     * @param string $apiProductId API側の製品ID
     * @return int|null 取得したID、または見つからない場合はnull
     * @throws PDOException データベース操作中にエラーが発生した場合
     */
    public function getRawApiDataId(string $sourceName, string $apiProductId): ?int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM raw_api_data WHERE source_name = :source_name AND api_product_id = :api_product_id");
        try {
            $stmt->execute([
                ':source_name' => $sourceName,
                ':api_product_id' => $apiProductId
            ]);
            $id = $stmt->fetchColumn();
            return $id !== false ? (int)$id : null;
        } catch (PDOException $e) {
            $errorMessage = "Failed to get raw_api_data ID for product '{$apiProductId}': " . $e->getMessage();
            $this->logger->error($errorMessage);
            throw new PDOException($errorMessage, (int)$e->getCode());
        }
    }
}
