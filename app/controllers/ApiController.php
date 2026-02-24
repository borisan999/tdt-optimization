<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('serialize_precision', -1);

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../models/Dataset.php"; 
require_once __DIR__ . "/../helpers/ExcelProcessor.php";
require_once __DIR__ . "/../services/CanonicalValidator.php";
require_once __DIR__ . "/../services/CanonicalNormalizer.php";

use App\Services\CanonicalValidator;
use App\Services\CanonicalValidationException;
use App\Services\CanonicalNormalizer;

class ApiController
{
    private $pdo;

    public function __construct()
    {
        $db = new Database();
        $this->pdo = $db->getConnection();
    }

    /**
     * Handles JSON API responses consistently.
     */
    private function jsonResponse(bool $success, ?array $data = null, ?array $error = null, int $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $success,
            'data'    => $data,
            'error'   => $error
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function handleRequest(string $path)
    {
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'POST' && preg_match('/^\/api\/datasets$/', $path)) {
            $this->createDataset();
        } elseif ($method === 'POST' && preg_match('/^\/api\/optimizations$/', $path)) {
            $this->createOptimization();
        } elseif ($method === 'GET' && preg_match('/^\/api\/optimizations\/history$/', $path)) {
            $this->getOptimizationHistory();
        } elseif ($method === 'GET' && preg_match('/^\/api\/optimizations\/(\d+)$/', $path, $matches)) {
            $this->getOptimizationDetail((int)$matches[1]);
        } elseif ($method === 'DELETE' && preg_match('/^\/api\/optimizations\/(\d+)$/', $path, $matches)) {
            $this->deleteOptimization((int)$matches[1]);
        } elseif ($method === 'POST' && preg_match('/^\/api\/upload\/excel$/', $path)) {
            $this->uploadExcel();
        } elseif ($method === 'POST' && preg_match('/^\/api\/template\/generate$/', $path)) {
            $this->generateFromTemplate();
        } elseif ($method === 'GET' && preg_match('/^\/api\/lang\/(en|es)$/', $path, $matches)) {
            $this->changeLanguage($matches[1]);
        } elseif ($method === 'GET' && preg_match('/^\/api\/catalogs$/', $path)) {
            $this->getCatalogs();
        } else {
            $this->jsonResponse(false, null, ['code' => 'NOT_FOUND', 'message' => 'Endpoint not found'], 404);
        }
    }

    private function generateFromTemplate()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $this->jsonResponse(false, null, ['code' => 'INVALID_INPUT', 'message' => 'No input provided'], 400);
            return;
        }

        try {
            $pythonBin = "/usr/bin/python3";
            $pythonScript = realpath(__DIR__ . "/../python/10/template_to_canonical.py");
            if (!file_exists($pythonScript)) throw new Exception('Python template script not found.');

            $descriptorspec = [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]];

            // Release session lock before long-running Python execution
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            $process = proc_open("{$pythonBin} {$pythonScript}", $descriptorspec, $pipes);
            if (!is_resource($process)) throw new Exception('Failed to start Python process.');

            fwrite($pipes[0], json_encode($input));
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            $returnCode = proc_close($process);

            if ($returnCode !== 0) {
                throw new Exception("Python script failed with exit code {$returnCode}. Stderr: {$stderr}");
            }

            $canonical = json_decode($stdout, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($canonical)) {
                throw new Exception('Malformed JSON response from Python script.');
            }

            // Persistence
            $dataset_name = $input['project_name'] ?? 'Template Dataset';
            $datasetModel = new Dataset();
            $uploadedBy = $_SESSION['user_id'] ?? 3;
            $datasetId = $datasetModel->createWithCanonical($canonical, $uploadedBy, 'pending', $dataset_name);

            $this->jsonResponse(true, ['dataset_id' => (int)$datasetId, 'message' => 'Dataset generated and saved successfully']);

        } catch (Exception $e) {
            $this->jsonResponse(false, null, ['code' => 'GENERATION_ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    private function changeLanguage(string $lang)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['lang'] = $lang;
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'dashboard'));
        exit;
    }

    private function getCatalogs()
    {
        try {
            require_once __DIR__ . '/../models/DerivadorModel.php';
            require_once __DIR__ . '/../models/RepartidorModel.php';
            require_once __DIR__ . '/../models/GeneralParams.php';

            $derivModel = new DerivadorModel();
            $repModel = new RepartidorModel();
            $genModel = new GeneralParams();

            $derivs = $derivModel->getAll();
            $reps = $repModel->getAll();
            $genParams = $genModel->getDefaults(); // Load defaults from dataset_id IS NULL

            $derivData = [];
            foreach ($derivs as $d) {
                $modelo = trim($d['modelo']);
                if ($modelo === '') continue;
                $derivData[$modelo] = [
                    'derivacion' => (float)$d['derivacion'],
                    'paso' => (float)$d['paso'],
                    'salidas' => (int)$d['salidas'],
                ];
            }

            $repData = [];
            foreach ($reps as $r) {
                $modelo = trim($r['modelo']);
                if ($modelo === '') continue;
                $repData[$modelo] = [
                    'perdida_insercion' => (float)$r['perdida_insercion'],
                    'salidas' => (int)$r['salidas'],
                ];
            }

            $this->jsonResponse(true, [
                'derivadores_data' => $derivData,
                'repartidores_data' => $repData,
                'general_params' => $genParams
            ]);
        } catch (Exception $e) {
            $this->jsonResponse(false, null, ['code' => 'CATALOG_ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    private function createDataset()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $canonical = $input['canonical'] ?? null;
        $dataset_name = $input['dataset_name'] ?? null;

        if (!$canonical || !is_array($canonical)) {
            $this->jsonResponse(false, null, ['code' => 'VALIDATION_ERROR', 'message' => 'Invalid or missing canonical JSON'], 400);
            return;
        }

        try {
            // 1. Validation
            $validation = CanonicalValidator::validate($canonical);
            if (!$validation['success']) {
                $this->jsonResponse(false, null, $validation['error'], 422);
                return;
            }

            // 2. Normalization occurs inside createWithCanonical in the Model
            $datasetModel = new Dataset();
            $uploadedBy = $_SESSION['user_id'] ?? 3; // Fallback to confirmed valid user
            $datasetId = $datasetModel->createWithCanonical($canonical, $uploadedBy, 'pending', $dataset_name);

            $this->jsonResponse(true, ['dataset_id' => (int)$datasetId, 'message' => 'Dataset created successfully']);

        } catch (Exception $e) {
            $this->jsonResponse(false, null, ['code' => 'DATABASE_ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    private function createOptimization()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $datasetId = $input['dataset_id'] ?? null;
        $parameters = $input['parameters'] ?? null;

        if (!$datasetId || !is_numeric($datasetId) || !is_array($parameters)) {
            $this->jsonResponse(false, null, ['code' => 'VALIDATION_ERROR', 'message' => 'Invalid inputs'], 400);
            return;
        }

        try {
            // HARDENING: Verify Dataset Integrity before allowing optimization
            $datasetModel = new Dataset();
            $dataset = $datasetModel->get($datasetId);
            
            if (!$dataset) {
                $this->jsonResponse(false, null, ['code' => 'NOT_FOUND', 'message' => 'Dataset not found'], 404);
                return;
            }

            $currentHash = hash('sha256', $dataset['canonical_json']);
            if ($currentHash !== $dataset['canonical_hash']) {
                $this->jsonResponse(false, null, [
                    'code' => 'INTEGRITY_ERROR', 
                    'message' => 'Dataset corruption detected: content does not match hash.'
                ], 500);
                return;
            }

            $stmt = $this->pdo->prepare("INSERT INTO optimizations (dataset_id, parameters_json, status, created_at) VALUES (?, ?, 'queued', NOW())");
            $stmt->execute([$datasetId, json_encode($parameters, JSON_UNESCAPED_UNICODE)]);
            $optId = $this->pdo->lastInsertId();
            
            $this->jsonResponse(true, ['opt_id' => (int)$optId, 'status' => 'queued']);
        } catch (PDOException $e) {
            $this->jsonResponse(false, null, ['code' => 'DATABASE_ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    private function uploadExcel()
    {
        if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            $this->jsonResponse(false, null, ['code' => 'UPLOAD_ERROR', 'message' => 'No file uploaded'], 400);
            return;
        }

        try {
            // Use filename as default name
            $dataset_name = $_POST['dataset_name'] ?? pathinfo($_FILES['excel_file']['name'], PATHINFO_FILENAME);

            // 1. Produce raw structure from Excel
            $rawCanonical = ExcelProcessor::readToCanonicalJson($_FILES['excel_file']['tmp_name']);

            // 2. Structural Validation Phase
            $validation = CanonicalValidator::validate($rawCanonical);
            if (!$validation['success']) {
                $this->jsonResponse(false, null, $validation['error'], 422);
                return;
            }

            // 3. Normalization (Lexicographic key sorting)
            $normalized = CanonicalNormalizer::normalize($rawCanonical);

            // 4. Persistence (Normalization -> JSON -> Hash -> Store)
            $datasetModel = new Dataset();
            $uploadedBy = $_SESSION['user_id'] ?? 3; // Fallback to a confirmed valid user_id if session is empty
            $datasetId = $datasetModel->createWithCanonical($normalized, $uploadedBy, 'pending', $dataset_name);

            $this->jsonResponse(true, ['dataset_id' => (int)$datasetId]);

        } catch (Exception $e) {
            $this->jsonResponse(false, null, ['code' => 'PROCESSING_ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    private function getOptimizationHistory()
    {
        try {
            $limit = (int)($_GET['limit'] ?? 20);
            $stmt = $this->pdo->prepare("SELECT opt_id, created_at, dataset_id, parameters_json, status FROM optimizations ORDER BY created_at DESC LIMIT ?");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$row) {
                $row['parameters'] = json_decode($row['parameters_json'], true);
                unset($row['parameters_json']);
            }
            $this->jsonResponse(true, ['items' => $rows]);
        } catch (PDOException $e) {
            $this->jsonResponse(false, null, ['code' => 'DATABASE_ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    private function getOptimizationDetail(int $optId)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT o.*, r.summary_json, r.detail_json FROM optimizations o LEFT JOIN results r ON o.opt_id = r.opt_id WHERE o.opt_id = ?");
            $stmt->execute([$optId]);
            $opt = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$opt) {
                $this->jsonResponse(false, null, ['code' => 'NOT_FOUND', 'message' => 'Not found'], 404);
                return;
            }
            $opt['parameters'] = json_decode($opt['parameters_json'], true);
            $opt['result'] = $opt['summary_json'] ? ['summary' => json_decode($opt['summary_json'], true)] : null;
            $this->jsonResponse(true, $opt);
        } catch (PDOException $e) {
            $this->jsonResponse(false, null, ['code' => 'DATABASE_ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    private function deleteOptimization(int $optId)
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM optimizations WHERE opt_id = ?");
            $stmt->execute([$optId]);
            $this->jsonResponse(true, ['message' => 'Deleted']);
        } catch (PDOException $e) {
            $this->jsonResponse(false, null, ['code' => 'DATABASE_ERROR', 'message' => $e->getMessage()], 500);
        }
    }
}