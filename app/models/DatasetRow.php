<?php
require_once __DIR__ . "/../config/db.php";

class DatasetRow
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Insert a single dataset row
     */
    public function addRow($dataset_id, $record_index, $field_name, $field_value, $unit = null)
    {
        $sql = "INSERT INTO dataset_rows (dataset_id, record_index, field_name, field_value, unit)
                VALUES (:dataset_id, :record_index, :field_name, :field_value, :unit)";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':dataset_id'   => $dataset_id,
            ':record_index' => $record_index,
            ':field_name'   => $field_name,
            ':field_value'  => $field_value,
            ':unit'         => $unit
        ]);
    }

    /**
     * Get all rows for a dataset
     */
    public function getRowsByDataset($dataset_id)
    {
        $sql = "SELECT * FROM dataset_rows 
                WHERE dataset_id = :dataset_id 
                ORDER BY record_index ASC, row_id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':dataset_id' => $dataset_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /**
     * Build structured data ready for JSON and Python
     */
    public function buildStructuredData($dataset_id)
    {
        $rows = $this->getRowsByDataset($dataset_id);

        $structured = [
            "apartments" => [],   // record_index = 0
            "tus"        => []    // record_index = 1
        ];

        foreach ($rows as $row) {
            $idx = $row["record_index"];

            if (!isset($structured["apartments"][$idx]) && $idx == 0) {
                $structured["apartments"][$idx] = [];
            }
            if (!isset($structured["tus"][$idx]) && $idx == 1) {
                $structured["tus"][$idx] = [];
            }

            if ($idx == 0) {
                $structured["apartments"][$idx][$row["field_name"]] = $row["field_value"];
            } elseif ($idx == 1) {
                $structured["tus"][$idx][$row["field_name"]] = $row["field_value"];
            }
        }

        // Re-index arrays (remove gaps)
        $structured["apartments"] = array_values($structured["apartments"]);
        $structured["tus"]        = array_values($structured["tus"]);

        return $structured;
    }

    public function deleteRowsByDataset($dataset_id)
    {
        $sql = "DELETE FROM dataset_rows WHERE dataset_id = :dataset_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':dataset_id' => $dataset_id]);
    }


}
