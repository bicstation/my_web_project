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
    private Database $database; // Databaseインスタンスも保持する

    /**
     * DbBatchInsert constructor.
     * @param Database $database Databaseインスタンス
     * @param Logger $logger ロギングのためのLoggerインスタンス
     */
    public function __construct(Database $database, Logger $logger)
    {
        $this->database = $database; // Databaseインスタンスを保存
        $this->pdo = $database->getConnection();
        $this->logger = $logger;
        $this->logger->log("DbBatchInsert initialized.");
    }

    /**
     * Databaseインスタンスを返す (processClassificationData からのアクセス用)
     * @return Database
     */
    public function getDatabase(): Database
    {
        return $this->database;
    }

    /**
     * 指定されたテーブルに複数のレコードをバルク挿入またはUPSERTします。
     * MySQLのON DUPLICATE KEY UPDATE句を利用します。
     *
     * @param string $tableName 挿入対象のテーブル名
     * @param array $data 挿入するレコードの配列。各要素はキーがカラム名、値がデータとなる連想配列である必要があります。
     * 例: [['col1' => 'valA', 'col2' => 'valB'], ['col1' => 'valC', 'col2' => 'valD']]
     * @param array $updateColumns UPSERT時に更新するカラムの配列。空の場合はON DUPLICATE KEY UPDATEをスキップし、
     * 重複キー違反でエラーになります。(または`uniqueColumns`が指定されていればそれを使用)
     * @param array $uniqueColumns ON DUPLICATE KEY UPDATE句で重複と判断するキーとして機能するカラムの配列。
     * 通常、プライマリキーまたはUNIQUEキー制約が設定されているカラムを指定します。
     * `$updateColumns`が空でこれが指定されている場合、「重複したら何もしない」動作になります。
     * @return int 挿入または更新された行の合計数
     * @throws PDOException データベース操作中にエラーが発生した場合
     */
    public function insertOrUpdate(string $tableName, array $data, array $updateColumns = [], array $uniqueColumns = []): int
    {
        if (empty($data)) {
            $this->logger->log("No data provided for batch insert into '{$tableName}'. Skipping.");
            return 0;
        }

        $firstRow = $data[0];
        $columns = array_keys($firstRow);
        $columnNames = implode('`, `', $columns); // カラム名をバッククォートで囲む
        $placeholders = ':' . implode(', :', $columns);

        $sql = "INSERT INTO `{$tableName}` (`{$columnNames}`) VALUES ({$placeholders})";

        // ON DUPLICATE KEY UPDATE ロジックの構築
        $onDuplicateKeyUpdateParts = [];
        if (!empty($updateColumns)) {
            // 更新するカラムが指定されている場合
            foreach ($updateColumns as $col) {
                // `updated_at` は自動更新される場合は含めない、またはVALUESで明示的に更新
                $onDuplicateKeyUpdateParts[] = "`{$col}` = VALUES(`{$col}`)";
            }
            $this->logger->log("Performing UPSERT into '{$tableName}'. Update columns: " . implode(', ', $updateColumns));
        } elseif (!empty($uniqueColumns)) {
            // 更新するカラムが指定されていないが、ユニークカラムが指定されている場合（重複時に何もしない）
            // MySQLでは、ON DUPLICATE KEY UPDATEで最低1つのカラムを更新する必要があるため、
            // ユニークキーの最初のカラムをそれ自身で更新することで実質的に何もしない。
            // あるいは、PRIMARY KEYがあれば `id = id` のようにする。
            // ここでは、`products` と中間テーブルのユニークキーを想定し、
            // 最初のユニークカラムをそれ自身で更新する（または何もしないよう `id = id` などを使う）。
            // たとえば、`product_id` と `category_id` がユニークキーなら、
            // `product_id = VALUES(product_id)` のようにする。
            // もしテーブルに `id` (PRIMARY KEY) があれば `id = id` が最も安全。
            // ここでは、データに必ず存在する最初のカラムを仮に利用。
            if (isset($firstRow['id'])) { // idカラムが存在する場合
                $onDuplicateKeyUpdateParts[] = "`id` = VALUES(`id`)"; // idは通常AUTO_INCREMENTなのでVALUESを使えない。`id = id`がより適切だが、MySQL 8.0以降ではVALUES(id)も機能することがある。
                                                                   // より安全なのは `id = id`
                $onDuplicateKeyUpdateParts = ["`id` = `id`"]; // MySQL 8.0+ではVALUES(col)が機能するが、最も安全なのは`col = col`
            } elseif (!empty($uniqueColumns)) { // idがなくてもユニークカラムがあればその最初のものを使う
                $firstUniqueCol = $uniqueColumns[0];
                $onDuplicateKeyUpdateParts[] = "`{$firstUniqueCol}` = VALUES(`{$firstUniqueCol}`)";
            } else {
                 // ここに到達することは稀だが、更新すべきカラムもユニークキーもない場合
                 // ON DUPLICATE KEY UPDATE 句は生成しない = 標準INSERT
            }
            $this->logger->log("Performing INSERT/IGNORE (no update on duplicate) into '{$tableName}' based on unique columns: " . implode(', ', $uniqueColumns));
        }

        if (!empty($onDuplicateKeyUpdateParts)) {
            $sql .= " ON DUPLICATE KEY UPDATE " . implode(', ', $onDuplicateKeyUpdateParts);
        } else {
            // updateColumnsもuniqueColumnsも指定されていない場合、通常のINSERT
            $this->logger->log("Performing standard INSERT into '{$tableName}'. (No ON DUPLICATE KEY UPDATE clause)");
        }

        $stmt = $this->pdo->prepare($sql);
        $insertedOrUpdatedCount = 0;

        // トランザクションは呼び出し元 (process_duga_api.php) で管理されるため、ここでは個別のトランザクションは開始しない
        // insertOrUpdate自体はバッチ処理の一部であり、全体トランザクション内で実行されるべき
        try {
            foreach ($data as $row) {
                // MySQL 8.0+ では VALUES(`col`) が AUTO_INCREMENT カラムにも使える可能性があるが、
                // 古いバージョンや安全性を考慮すると `id = id` のような静的な更新が安全。
                // ただし、ここでは挿入されるデータ (`$row`) にIDは含まれないはずなので、問題ない。
                $stmt->execute($row);
                $insertedOrUpdatedCount += $stmt->rowCount(); // affected rows (for INSERT and UPDATE)
            }
            $this->logger->log("Successfully inserted/updated {$insertedOrUpdatedCount} rows into '{$tableName}'.");
        } catch (PDOException $e) {
            // 親のトランザクションがロールバックされるため、ここでは個別のロールバックは不要
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