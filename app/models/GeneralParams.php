<?php
require_once __DIR__ . "/../config/db.php";

class GeneralParams
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Save all general parameters for a dataset
     * Receives an associative array:
     *   ["Piso_Maximo" => "15", "Apartamentos_Piso" => "4", ...]
     */
    public function saveForDataset($dataset_id, $params)
    {
        if (!$dataset_id || empty($params)) {
            return false;
        }

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
}
?>