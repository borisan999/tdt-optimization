<?php
require_once __DIR__ . "/../config/db.php";

class GeneralParams
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = (new Database())->getConnection();
    }

   /**
     * Save all general parameters for a dataset
     * Overwrites existing parameters for that dataset
     */
    public function saveForDataset($dataset_id, $params)
    {
        if (!$dataset_id || empty($params)) {
            return false;
        }

        // 1. Clear existing parameters for this dataset
        $delete = $this->pdo->prepare(
            "DELETE FROM parametros_generales WHERE dataset_id = :dataset_id"
        );
        $delete->execute([':dataset_id' => $dataset_id]);

        // 2. Insert fresh values
        $sql = "INSERT INTO parametros_generales (dataset_id, param_name, param_value)
                VALUES (:dataset_id, :param_name, :param_value)";
        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $name => $value) {
            $stmt->execute([
                ':dataset_id' => $dataset_id,
                ':param_name' => $name,
                ':param_value' => $value
            ]);
        }

        return true;
    }
    /**
     * Get associative array of all parameters for a dataset
     * Returns:
     *   ["Piso_Maximo" => "15", "Apartamentos_Piso" => "4", ...]
     */
    public function getByDataset($dataset_id)
    {
        $sql = "SELECT param_name, param_value
                FROM parametros_generales
                WHERE dataset_id = :dataset_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':dataset_id' => $dataset_id]);

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $params = [];
        foreach ($results as $r) {
            $params[$r['param_name']] = $r['param_value'];
        }

        return $params;
    }

    /**
     * Get associative array of all global default parameters
     * dataset_id IS NULL in the database for these values
     */
    public function getDefaults()
    {
        $sql = "SELECT param_name, param_value
                FROM parametros_generales
                WHERE dataset_id IS NULL";

        $stmt = $this->pdo->query($sql);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $params = [];
        foreach ($results as $r) {
            $params[$r['param_name']] = $r['param_value'];
        }

        return $params;
    }

    /**
     * Save global default parameters
     */
    public function saveDefaults($params)
    {
        try {
            $this->pdo->beginTransaction();

            // 1. Clear existing defaults
            $delete = $this->pdo->prepare(
                "DELETE FROM parametros_generales WHERE dataset_id IS NULL"
            );
            $delete->execute();

            // 2. Insert fresh values
            $sql = "INSERT INTO parametros_generales (dataset_id, param_name, param_value)
                    VALUES (NULL, :param_name, :param_value)";
            $stmt = $this->pdo->prepare($sql);

            foreach ($params as $name => $value) {
                $stmt->execute([
                    ':param_name' => $name,
                    ':param_value' => $value
                ]);
            }

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Error saving global defaults: " . $e->getMessage());
            return false;
        }
    }
}
?>