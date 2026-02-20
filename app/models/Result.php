<?php
require_once __DIR__ . "/../config/db.php";

class Result
{
    private $pdo;

    public function __construct()
    {
        $db = new Database();
        $this->pdo = $db->getConnection();
    }

    /**
     * Insert a row into the results table
     */
       public function insert($dataset_id, $summary, $status, $duration, $raw_json)
        {
            $sql = "INSERT INTO results 
                    (dataset_id, summary, status, duration, raw_json)
                    VALUES
                    (:dataset_id, :summary, :status, :duration, :raw_json)";

            $stmt = $this->pdo->prepare($sql);

            $stmt->execute([
                ":dataset_id" => $dataset_id,
                ":summary"    => $summary,
                ":status"     => $status,
                ":duration"   => $duration,
                ":raw_json"   => $raw_json
            ]);

            return $this->pdo->lastInsertId();  // ← YOU USE THIS AS opt_id FOR RESULTS_DETAIL
        }

    /**
     * Save a result JSON for a dataset
     */
    public function saveResult($dataset_id, $opt_id, $summary_json, $detail_json, $inputs_json = '{}')
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO results (dataset_id, opt_id, summary_json, detail_json, inputs_json)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([$dataset_id, $opt_id, $summary_json, $detail_json, $inputs_json]);
    }

    /**
     * Fetch all results for a given optimization ID
     */
    public function getByOptId($opt_id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM results WHERE opt_id = :id ORDER BY result_id ASC");
        $stmt->execute([":id" => $opt_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieve one parameter by name
     */
    public function getParam($opt_id, $param)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM results WHERE opt_id = :id AND parameter = :p LIMIT 1");
        $stmt->execute([":id" => $opt_id, ":p" => $param]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
