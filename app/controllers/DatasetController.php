<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('serialize_precision', -1);

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../models/Dataset.php";
require_once __DIR__ . "/../models/Result.php";
require_once __DIR__ . "/../services/CanonicalValidator.php";
require_once __DIR__ . "/../services/CanonicalNormalizer.php";

use App\Services\CanonicalValidator;
use App\Services\CanonicalValidationException;
use App\Services\CanonicalNormalizer;

class DatasetController
{
    private $pdo;

    public function __construct()
    {
        $db = new Database();
        $this->pdo = $db->getConnection();
    }

    public function handleRequest(string $path)
    {
        $method = $_SERVER['REQUEST_METHOD'];

        // Basic routing logic
        if ($method === 'GET' && preg_match('/^\/dataset\/list$/', $path)) {
            $this->listDatasets();
        } elseif ($method === 'GET' && preg_match('/^\/dataset\/(\d+)$/', $path, $matches)) {
            $datasetId = (int)$matches[1];
            $this->getDataset($datasetId);
        } elseif ($method === 'POST' && strpos($path, '/dataset/update') !== false) {
            $this->updateDataset();
        } elseif ($method === 'GET' && preg_match('/^\/dataset\/history\/(\d+)$/', $path, $matches)) {
            $datasetId = (int)$matches[1];
            $this->getHistory($datasetId);
        } elseif ($method === 'GET' && preg_match('/^\/result\/(\d+)$/', $path, $matches)) {
            $optId = (int)$matches[1];
            $this->getResult($optId);
        } elseif ($method === 'POST' && preg_match('/^\/dataset\/run\/(\d+)$/', $path, $matches)) {
            $datasetId = (int)$matches[1];
            $this->runPython($datasetId);
        } else {
            $this->jsonResponse(['success' => false, 'message' => 'Endpoint not found or method not allowed'], 404);
        }
    }

    /**
     * GET /datasets
     */
    private function listDatasets()
    {
        try {
            $datasetModel = new Dataset();
            $datasets = $datasetModel->getAll();
            $this->jsonResponse(['success' => true, 'datasets' => $datasets]);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'message' => 'Failed to list datasets'], 500);
        }
    }

    /**
     * GET /dataset/{id}
     */
    private function getDataset(int $datasetId)
    {
        $stmt = $this->pdo->prepare("SELECT canonical_json, dataset_name FROM datasets WHERE dataset_id = :id");
        $stmt->execute(['id' => $datasetId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || empty($row['canonical_json'])) {
            $this->jsonResponse(['success' => false, 'message' => 'Dataset not found or no canonical JSON.'], 404);
            return;
        }

        $canonical = json_decode($row['canonical_json'], true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->jsonResponse(['success' => false, 'message' => 'Failed to decode canonical JSON from database.'], 500);
            return;
        }

        $this->jsonResponse([
            'success' => true, 
            'canonical' => $canonical,
            'dataset_name' => $row['dataset_name']
        ]);
    }

    /**
     * POST /dataset/update
     */
    public function updateDataset()
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $dataset_id = $_POST['dataset_id'] ?? $input['dataset_id'] ?? null;
            $canonical  = $_POST['canonical'] ?? $input['canonical'] ?? null;
            $dataset_name = $_POST['dataset_name'] ?? $input['dataset_name'] ?? null;

            if (!$dataset_id || !$canonical) {
                throw new Exception("Missing dataset_id or canonical.");
            }

            if (is_string($canonical)) {
                $canonical = json_decode($canonical, true);
            }

            if (!is_array($canonical)) {
                throw new Exception("Invalid canonical payload.");
            }

            // 1. Domain Validation Layer
            $validation = CanonicalValidator::validate($canonical);
            if (!$validation['success']) {
                $this->jsonResponse($validation, 422);
                return;
            }

            // 2. Normalization (Lexicographic key sorting)
            $normalized = CanonicalNormalizer::normalize($canonical);

            // 3. Serialization
            $json = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $hash = hash('sha256', $json);

            // 4. Persistence
            $stmt = $this->pdo->prepare("
                UPDATE datasets
                SET canonical_json = :json,
                    canonical_hash = :hash,
                    dataset_name = :name,
                    status = 'pending',
                    updated_at = NOW()
                WHERE dataset_id = :id
            ");

            $stmt->execute([
                'json' => $json,
                'hash' => $hash,
                'name' => $dataset_name,
                'id'   => $dataset_id
            ]);

            // Delete results automatically when dataset changes
            $stmt = $this->pdo->prepare("DELETE FROM results WHERE dataset_id = :dataset_id");
            $stmt->execute(['dataset_id' => $dataset_id]);

            $this->jsonResponse(['success' => true]);

        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error'   => [
                    'code' => 'SYSTEM_ERROR',
                    'message' => $e->getMessage()
                ]
            ], 400);
        }
    }

    private function runPython(int $datasetId)
    {
        $canonicalJson = '';
        $optId = null;
        $pythonResult = null;
        $errorMessage = '';

        try {
            list($canonicalJson, $optId, $status) = $this->initializeOptimization($datasetId);
            
            if ($status === 'running' || !$this->markOptimizationAsRunning((int)$optId)) {
                $this->jsonResponse(['success' => true, 'message' => 'Optimization already in progress', 'opt_id' => (int)$optId]);
                return;
            }

            // Release session lock before long-running Python execution
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            list($returnCode, $stdout, $stderr) = $this->executePython($canonicalJson);

            if ($returnCode !== 0) {
                throw new Exception("Python script failed with exit code {$returnCode}. Stderr: {$stderr}");
            }

            // Robust JSON extraction: look for the LAST JSON object in stdout
            $pythonResult = null;
            if (preg_match('/(\{"success":\s*(?:true|false).+\})$/s', $stdout, $matches)) {
                $pythonResult = json_decode($matches[1], true);
            }

            if (json_last_error() !== JSON_ERROR_NONE || !$pythonResult) {
                error_log("Raw Python stdout: " . $stdout);
                throw new Exception('Malformed or missing JSON response from Python. Check error logs.');
            }

            $solverStatus = $pythonResult['solver_status'] ?? 'UNKNOWN';
            $solverLog = $pythonResult['solver_log'] ?? null;

            $this->finalizeOptimization($datasetId, (int)$optId, $stdout, $canonicalJson, $pythonResult, true, '', $solverStatus, $solverLog);
            $this->jsonResponse(['success' => true, 'result' => $pythonResult, 'opt_id' => (int)$optId]);

        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            if ($optId !== null) {
                try {
                    $this->finalizeOptimization($datasetId, (int)$optId, null, null, null, false, $errorMessage, null, null);
                } catch (Exception $fe) {}
            } else {
                 try {
                     $stmt = $this->pdo->prepare("UPDATE datasets SET status = 'error' WHERE dataset_id = ?");
                     $stmt->execute([$datasetId]);
                 } catch (Exception $de) {}
            }
            $this->jsonResponse(['success' => false, 'message' => 'Optimization process failed: ' . $errorMessage], 500);
        }
    }

    private function initializeOptimization(int $datasetId): array
    {
        try {
            $this->pdo->beginTransaction();
            // EXCLUSIVE LOCK on the dataset to prevent concurrent initializations for the same dataset
            $stmt = $this->pdo->prepare("SELECT canonical_json FROM datasets WHERE dataset_id = :id FOR UPDATE");
            $stmt->execute(['id' => $datasetId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || empty($row['canonical_json'])) {
                throw new Exception('Dataset not found or no canonical JSON for execution.');
            }
            $canonicalJson = $row['canonical_json'];

            // Check if there is already a queued or running optimization
            $stmt = $this->pdo->prepare("SELECT opt_id, status FROM optimizations WHERE dataset_id = ? AND status IN ('queued', 'running')");
            $stmt->execute([$datasetId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $this->pdo->commit();
                return [$canonicalJson, (int)$existing['opt_id'], $existing['status']];
            }

            $stmt = $this->pdo->prepare("UPDATE datasets SET status = 'processing' WHERE dataset_id = ?");
            $stmt->execute([$datasetId]);

            // Enforce 1:1 Optimization per Dataset: Delete only finished/failed previous ones
            $stmt = $this->pdo->prepare("DELETE FROM optimizations WHERE dataset_id = ? AND status NOT IN ('queued', 'running')");
            $stmt->execute([$datasetId]);

            $stmt = $this->pdo->prepare("INSERT INTO optimizations (dataset_id, created_at, status, parameters_json) VALUES (?, NOW(), 'queued', '{}')");
            $stmt->execute([$datasetId]);
            $optId = $this->pdo->lastInsertId();

            $this->pdo->commit();
            return [$canonicalJson, (int)$optId, 'queued'];
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw new Exception('Failed to initialize optimization: ' . $e->getMessage());
        }
    }

    private function markOptimizationAsRunning(int $optId): bool
    {
        try {
            $this->pdo->beginTransaction();
            // Check current status first
            $stmt = $this->pdo->prepare("SELECT status FROM optimizations WHERE opt_id = ? FOR UPDATE");
            $stmt->execute([$optId]);
            $status = $stmt->fetchColumn();

            if ($status === 'running') {
                $this->pdo->commit();
                return false; // Already running
            }

            if ($status !== 'queued') {
                throw new Exception("Optimization {$optId} is in status '{$status}', expected 'queued'.");
            }

            $stmt = $this->pdo->prepare("UPDATE optimizations SET status = 'running', started_at = NOW() WHERE opt_id = ?");
            $stmt->execute([$optId]);
            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw new Exception('Failed to mark optimization as running: ' . $e->getMessage());
        }
    }

    private function executePython(string $canonicalJson): array
    {
        $pythonBin = "/usr/bin/python3";
        $pythonScript = realpath(__DIR__ . "/../python/10/optimizer_canonical.py");
        if (!file_exists($pythonScript)) throw new Exception('Python optimizer script not found.');

        $descriptorspec = [
            0 => ["pipe", "r"], // stdin
            1 => ["pipe", "w"], // stdout
            2 => ["pipe", "w"]  // stderr
        ];

        $process = proc_open("{$pythonBin} {$pythonScript}", $descriptorspec, $pipes);
        if (!is_resource($process)) throw new Exception('Failed to start Python process.');

        // Set non-blocking mode for reading pipes
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        // Write input to stdin
        fwrite($pipes[0], $canonicalJson);
        fclose($pipes[0]);

        $stdout = '';
        $stderr = '';
        $all_read_pipes = [$pipes[1], $pipes[2]];
        $write = null;
        $except = null;

        // Concurrent read loop to avoid deadlocks
        while (!empty($all_read_pipes)) {
            $read = $all_read_pipes; // stream_select modifies this array
            $changed = stream_select($read, $write, $except, 90); 
            
            if ($changed === false) {
                error_log("Stream select failed.");
                break;
            } elseif ($changed === 0) {
                error_log("Stream select timed out (30s).");
                break;
            }

            foreach ($read as $pipe) {
                $content = fread($pipe, 8192);
                if ($pipe === $pipes[1]) {
                    $stdout .= $content;
                } else {
                    $stderr .= $content;
                }

                if (feof($pipe)) {
                    $index = array_search($pipe, $all_read_pipes);
                    if ($index !== false) unset($all_read_pipes[$index]);
                    fclose($pipe);
                }
            }
        }

        $returnCode = proc_close($process);
        return [$returnCode, $stdout, $stderr];
    }

    private function finalizeOptimization(int $datasetId, int $optId, ?string $stdout, ?string $canonicalJson, ?array $pythonResult, bool $success, string $errorMessage = '', ?string $solverStatus = null, ?string $solverLog = null)
    {
        try {
            $this->pdo->beginTransaction();
            if ($success && $pythonResult) {
                $summary = $pythonResult['summary'] ?? $pythonResult;
                $detail = $pythonResult['detail'] ?? [];
                $solverStatus = $pythonResult['solver_status'] ?? 'UNKNOWN';
                $solverLog = $pythonResult['solver_log'] ?? null;
                
                $result = new Result();
                $result->saveResult(
                    $datasetId, 
                    $optId, 
                    json_encode($summary, JSON_UNESCAPED_UNICODE), 
                    json_encode($detail, JSON_UNESCAPED_UNICODE), 
                    $canonicalJson
                );
                $stmt = $this->pdo->prepare("UPDATE optimizations SET status = 'finished', finished_at = NOW(), solver_status = :solver_status, solver_log = :solver_log WHERE opt_id = :opt_id");
                $stmt->execute([
                    'solver_status' => $solverStatus,
                    'solver_log' => $solverLog,
                    'opt_id' => $optId
                ]);
                $stmt = $this->pdo->prepare("UPDATE datasets SET status = 'processed' WHERE dataset_id = ?");
                $stmt->execute([$datasetId]);
            } else {
                $stmt = $this->pdo->prepare("UPDATE optimizations SET status = 'failed', finished_at = NOW(), error_message = :error_message, solver_status = :solver_status, solver_log = :solver_log WHERE opt_id = :opt_id");
                $stmt->execute([
                    'error_message' => $errorMessage,
                    'solver_status' => $solverStatus ?? 'FAILED',
                    'solver_log' => $solverLog,
                    'opt_id' => $optId
                ]);
                $stmt = $this->pdo->prepare("UPDATE datasets SET status = 'error' WHERE dataset_id = ?");
                $stmt->execute([$datasetId]);
            }
            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw new Exception('Failed to finalize: ' . $e->getMessage());
        }
    }

    private function jsonResponse(array $data, int $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function getHistory(int $datasetId)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT o.opt_id, o.status, o.started_at, o.finished_at, r.result_id, (r.result_id IS NOT NULL) AS has_result, r.summary_json, o.created_at AS optimization_created_at
                FROM optimizations o
                LEFT JOIN results r ON r.opt_id = o.opt_id
                WHERE o.dataset_id = :dataset_id
                ORDER BY o.created_at DESC
            ");
            $stmt->execute(['dataset_id' => $datasetId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$row) {
                $row['summary'] = $row['summary_json'] ? json_decode($row['summary_json'], true) : null;
                unset($row['summary_json']);
            }
            $this->jsonResponse(['success' => true, 'history' => $rows]);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'message' => 'Failed to load history'], 500);
        }
    }

    public function getResult(int $optId)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT result_id, opt_id, dataset_id, summary_json, detail_json, inputs_json, meta_json, created_at FROM results WHERE opt_id = :opt_id LIMIT 1");
            $stmt->execute(['opt_id' => $optId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $this->jsonResponse(['success' => false, 'message' => 'Not found'], 404);
                return;
            }
            $result = [
                'result_id' => $row['result_id'], 'opt_id' => $row['opt_id'], 'dataset_id' => $row['dataset_id'], 'created_at' => $row['created_at'],
                'summary' => $row['summary_json'] ? json_decode($row['summary_json'], true) : null,
                'details' => $row['detail_json'] ? json_decode($row['detail_json'], true) : null,
                'inputs' => $row['inputs_json'] ? json_decode($row['inputs_json'], true) : null,
                'meta' => $row['meta_json'] ? json_decode($row['meta_json'], true) : null,
            ];
            $this->jsonResponse(['success' => true, 'result' => $result]);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'message' => 'Failed'], 500);
        }
    }
}
