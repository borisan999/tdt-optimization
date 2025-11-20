<?php
require_once __DIR__ . "/../config/db.php";

class Derivador
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Save all Derivadors
     */
    public function saveDerivador( $params)
    {
        if (!$dataset_id || empty($params)) {
            return false;
        }

        $sql = "INSERT INTO components_derivadores (id, modelo, derivacion, paso, salidas, perdida_insercion, frecuencia)
                VALUES (:id, :modelo, :derivacion, :paso, :salidas, :perdida_insercion, :frecuencia)";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $name => $value) {
            $stmt->execute([
                ':id' => $id,
                ':modelo' => $modelo,
                ':derivacion' => $derivacion,
                ':paso' => $paso,
                ':salidas' => $salidas,
                ':perdida_insercion' => $perdida_insercion,
                ':paso' => $frecuencia
            ]);
        }

        return true;
    }

    /**
     * Get all Derivadors
     */
    public function getDerivadors()
    {
        $sql = "SELECT id, modelo, derivacion, paso, salidas, perdida_insercion, frecuencia
                FROM components_derivadores";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $params = [];
        foreach ($results as $r) {
            $params[$r['param_name']] = $r['param_value'];
        }

        return $params;
    }
}
?>