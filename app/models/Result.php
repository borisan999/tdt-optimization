<?php
require_once __DIR__ . "/../config/db.php";

class Result
{
    private $pdo;

    public function __construct() {
        $db = new Database();
        $this->pdo = $db->getConnection();   // correct property
    }

    public function insert($opt_id, $parameter, $value, $unit, $deviation, $meta_json)
    {
        $sql = "INSERT INTO results (opt_id, parameter, value, unit, deviation, meta_json)
                VALUES (:opt_id, :parameter, :value, :unit, :deviation, :meta_json)";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ":opt_id"    => $opt_id,
            ":parameter" => $parameter,
            ":value"     => is_scalar($value) ? $value : json_encode($value),
            ":unit"      => $unit,
            ":deviation" => $deviation,
            ":meta_json" => $meta_json
        ]);
    }
}
