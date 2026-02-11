<?php
require_once __DIR__ . "/../config/db.php";

class DatasetRow
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = (new Database())->getConnection();
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

        $records = [];
        foreach ($rows as $row) {
            $idx = $row['record_index'];
            $field = $row['field_name'];
            $value = $row['field_value'];
            $records[$idx][$field] = $value;
        }

        $apartments = [];
        $tus = [];
        $apartmentKeys = []; // To ensure unique apartments

        foreach ($records as $record) {
            if (isset($record['tus_requeridos'])) { // This is an apartment record
                $key = $record['piso'] . '-' . $record['apartamento'];
                if (!isset($apartmentKeys[$key])) {
                    $apartmentKeys[$key] = true;
                    $apartments[] = [
                        'piso'                   => (int)$record['piso'],
                        'apartamento'            => (int)$record['apartamento'],
                        'tus_requeridos'         => (int)$record['tus_requeridos'],
                        'largo_cable_derivador'  => (float)$record['largo_cable_derivador'],
                        'largo_cable_repartidor' => (float)$record['largo_cable_repartidor'],
                        // Do NOT add 'tus' here if we are returning them separately
                    ];
                }
            } elseif (isset($record['tu_index'])) { // This is a TU record
                $tus[] = [
                    'piso'           => (int)$record['piso'],
                    'apartamento'    => (int)$record['apartamento'],
                    'tu_index'       => (int)$record['tu_index'],
                    'largo_cable_tu' => (float)$record['largo_cable_tu'],
                ];
            }
        }

        return [
            'apartments' => $apartments,
            'tus'        => $tus,
        ];
    }



    public function deleteRowsByDataset($dataset_id)
    {
        $sql = "DELETE FROM dataset_rows WHERE dataset_id = :dataset_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':dataset_id' => $dataset_id]);
    }


}
