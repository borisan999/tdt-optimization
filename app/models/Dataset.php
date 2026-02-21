<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../services/CanonicalNormalizer.php";

use App\Services\CanonicalNormalizer;

class Dataset
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = (new Database())->getConnection();
    }

    /**
     * Create a dataset record with canonical JSON.
     * Ensures normalization occurs before hashing and persistence.
     */
    public function createWithCanonical(array $canonicalJsonArray, $uploaded_by = 1, $status = "pending", $dataset_name = null)
    {
        // 1. Normalize
        $normalized = CanonicalNormalizer::normalize($canonicalJsonArray);
        
        // 2. Serialize and Hash
        $json = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $hash = hash('sha256', $json);

        $sql = "INSERT INTO datasets (uploaded_by, status, canonical_json, canonical_hash, dataset_name) 
                VALUES (:uploaded_by, :status, :canonical_json, :canonical_hash, :dataset_name)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':uploaded_by' => $uploaded_by,
            ':status' => $status,
            ':canonical_json' => $json,
            ':canonical_hash' => $hash,
            ':dataset_name' => $dataset_name
        ]);

        return $this->pdo->lastInsertId();
    }

    /**
     * Update dataset name
     */
    public function updateName($dataset_id, $name)
    {
        $stmt = $this->pdo->prepare("UPDATE datasets SET dataset_name = ? WHERE dataset_id = ?");
        return $stmt->execute([$name, $dataset_id]);
    }

    /**
     * Get dataset by ID
     */
    public function get($dataset_id)
    {
        $sql = "SELECT * FROM datasets WHERE dataset_id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $dataset_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * List all datasets
     */
    public function getAll()
    {
        $sql = "SELECT * FROM datasets ORDER BY created_at DESC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Return all datasets for History screen with their latest optimization ID
     */
    public function getHistory()
    {
        $sql = "SELECT d.*, 
                (SELECT opt_id FROM optimizations WHERE dataset_id = d.dataset_id ORDER BY created_at DESC LIMIT 1) as latest_opt_id
                FROM datasets d 
                ORDER BY d.created_at DESC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get the single optimization run for this dataset (Enforced 1:1)
     */
    public function getLatestOptimization($dataset_id)
    {
        $sql = "SELECT o.*, r.summary_json 
                FROM optimizations o 
                LEFT JOIN results r ON o.opt_id = r.opt_id 
                WHERE o.dataset_id = :id 
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $dataset_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
