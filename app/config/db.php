<?php


require_once __DIR__ . "/../../vendor/autoload.php";

use Dotenv\Dotenv;

class Database {
    private $conn;

    public function __construct() {
        $this->loadEnv();
    }

    private function loadEnv() {
        // Calculate the correct root path
        $root = realpath(__DIR__ . '/../../');

        if (!file_exists($root . '/.env')) {
            die("❌ ERROR: .env not found in: $root");
        }

        $dotenv = Dotenv::createImmutable($root);
        $dotenv->load();
    }

    public function getConnection() {
        if ($this->conn) {
            return $this->conn;
        }

        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $dbname = $_ENV['DB_NAME'] ?? 'tdt_optimization';
        $user = $_ENV['DB_USER'] ?? 'root';
        $pass = $_ENV['DB_PASS'] ?? '';

        try {
            $this->conn = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e) {
            die("❌ Database connection error: " . $e->getMessage());
        }

        return $this->conn;
    }
}