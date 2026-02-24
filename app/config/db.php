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
             // Silently fail or log? The previous code died.
             return;
        }

        try {
            $dotenv = Dotenv::createImmutable($root);
            $loaded = $dotenv->load();
            foreach ($loaded as $key => $value) {
                $_ENV[$key] = $value;
            }
        } catch (\Exception $e) {
            // Log error
        }
    }

    public function getConnection() {
        if ($this->conn) {
            return $this->conn;
        }

        $host = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1';
        $dbname = $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? getenv('DB_NAME') ?: '';
        $user = $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? getenv('DB_USER') ?: '';
        $pass = $_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? getenv('DB_PASS') ?: '';

        if (empty($dbname) || empty($user)) {
            die("❌ ERROR: Database configuration missing. Check your .env file.");
        }

        try {
            $this->conn = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            die("❌ Database connection error: " . $e->getMessage() . " (Host: $host, DB: $dbname, User: $user)");
        }

        return $this->conn;
    }
}