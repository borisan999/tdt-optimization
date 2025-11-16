<?php
require_once __DIR__ . "/../config/db.php";

class Dataset
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Create a dataset record
     */
    public function create($uploaded_by, $status = "pending")
    {
        $sql = "INSERT INTO datasets (uploaded_by, status) 
                VALUES (:uploaded_by, :status)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':uploaded_by' => $uploaded_by,
            ':status' => $status
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
