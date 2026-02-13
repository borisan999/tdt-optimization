<?php

declare(strict_types=1);

namespace app\controllers;

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/ResultParser.php';
require_once __DIR__ . '/../services/ResultComplianceService.php'; // Include the new service

use app\helpers\ResultParser;
use app\services\ResultComplianceService;
use PDO;
use RuntimeException;
use DateTime;

class ResultsController
{
    public int $opt_id;
    public array $meta = [];
    public array $summary = [];
    public array $details = [];
    public array $inputs = [];
    public array $warnings = [];
    public array $violations = [];
    public ?string $created = null;
    public string $status = 'unknown';

    public function __construct(int $opt_id)
    {
        if ($opt_id <= 0) {
            throw new RuntimeException('opt_id is required');
        }
        $this->opt_id = $opt_id;

        $this->loadData();
    }

    private function loadData(): void
    {
        // 1. Fetch DB Row
        $DB  = new \Database();
        $pdo = $DB->getConnection();

        $sql = "
            SELECT
                o.opt_id, o.dataset_id, o.status, o.created_at,
                r.summary_json, r.detail_json, r.inputs_json
            FROM optimizations o
            LEFT JOIN results r ON r.opt_id = o.opt_id
            WHERE o.opt_id = :opt_id
        ";
        $st = $pdo->prepare($sql);
        $st->execute(['opt_id' => $this->opt_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new RuntimeException('Optimization not found');
        }
        
        $this->status = $row['status'] ?? 'unknown';
        if (isset($row['created_at'])) {
            $this->created = (new DateTime($row['created_at']))->format('Y-m-d H:i:s');
        }

        // 2. Guard Clause for missing results
        if (empty($row['detail_json'])) {
            $this->meta = [
                'opt_id' => $row['opt_id'],
                'dataset_id' => $row['dataset_id'],
                'status' => $this->status,
                'created_at' => $this->created
            ];
            // Leave details, summary, etc., as empty arrays
            return;
        }

        // 3. Parse via ResultParser
        $parser = ResultParser::fromDbRow($row);
        
        $canonical = $parser->canonical();
        $this->meta = $parser->meta();
        $this->warnings = $parser->warnings();
        $this->summary = $canonical['summary'] ?? [];
        $this->details = $canonical['detail'] ?? [];
        $this->inputs = $canonical['inputs'] ?? [];
        
        // 4. Use Service for Violation Calculation
        $this->violations = ResultComplianceService::calculateViolations($this->details);
    }
}
