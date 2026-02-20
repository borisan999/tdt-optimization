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
    public function createWithCanonical(array $canonicalJsonArray, $uploaded_by = 1, $status = "pending")
    {
        // 1. Normalize
        $normalized = CanonicalNormalizer::normalize($canonicalJsonArray);
        
        // 2. Serialize and Hash
        $json = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $hash = hash('sha256', $json);

        $sql = "INSERT INTO datasets (uploaded_by, status, canonical_json, canonical_hash) 
                VALUES (:uploaded_by, :status, :canonical_json, :canonical_hash)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':uploaded_by' => $uploaded_by,
            ':status' => $status,
            ':canonical_json' => $json,
            ':canonical_hash' => $hash
        ]);

        return $this->pdo->lastInsertId();
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
     * Return all datasets for History screen
     */
    public function getHistory()
    {
        $sql = "SELECT * FROM datasets ORDER BY created_at DESC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }
}
