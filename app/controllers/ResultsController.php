<?php

declare(strict_types=1);

namespace app\controllers;

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/ResultParser.php';
require_once __DIR__ . '/../services/ResultComplianceService.php';
require_once __DIR__ . '/../viewmodels/ResultViewModel.php';

use app\helpers\ResultParser;
use app\services\ResultComplianceService;
use app\viewmodels\ResultViewModel;
use PDO;
use RuntimeException;
use DateTime;

class ResultsController
{
    private int $opt_id;

    public function __construct(int $opt_id)
    {
        $this->opt_id = $opt_id;
    }

    public function execute(): array
    {
        try {
            // STEP 0.2 — Validation & Fetching
            $resultId = $this->opt_id;
            if (!$resultId) {
                throw new \DomainException('Missing result ID.');
            }

            $row = $this->fetchResultRow($resultId); // Implement DB fetch
            if (!$row) {
                throw new \DomainException('Result not found.');
            }

            if (!isset($row['detail_json']) || trim($row['detail_json']) === '') {
                throw new \DomainException('Missing detail_json in DB row.');
            }

            $parser = \app\helpers\ResultParser::fromDbRow($row);
            $summary = $parser->summary();

            // Detect Infeasible Result from summary
            if (isset($summary['success']) && $summary['success'] === false) {
                return [
                    'status' => 'infeasible',
                    'message' => $summary['message'] ?? 'Solver could not find a feasible solution.',
                    'dataset_id' => $row['dataset_id'],
                    'opt_id' => $row['opt_id']
                ];
            }

            // STEP 0.3 — Canonical validation
            $canonical = $parser->canonical(); // Already validates internally

            // STEP 0.4 — Optional compliance / metrics
            $violations = [];
            try {
                $violations = \app\services\ResultComplianceService::calculateViolations($canonical['detail']);
            } catch (\Throwable $e) {
                error_log("Compliance calculation error: " . $e->getMessage());
                // Keep $violations empty — don’t break view
            }

            // STEP 0.5 — Normalize meta (DateTime -> string)
            $meta = $parser->meta();
            if (isset($meta['created_at']) && $meta['created_at'] instanceof \DateTimeInterface) {
                $meta['created_at'] = $meta['created_at']->format('Y-m-d H:i:s');
            }
            // Merge the full database row into meta to include solver_status and solver_log
            $meta = array_merge($row, $meta);

            return [
                'status'    => 'success',
                'viewModel' => new \app\viewmodels\ResultViewModel(
                    $canonical['detail'],
                    $canonical['summary'],
                    $canonical['inputs'],
                    $meta,
                    $violations,
                    $row,
                    $row['dataset_name'] ?? null
                ),
            ];

        } catch (\DomainException $e) {
            error_log("ResultParser DomainException: " . $e->getMessage());
            return [
                'status' => 'error',
                'error_type' => 'parser_error',
                'message' => 'Unable to parse result safely.',
            ];
        } catch (\RuntimeException $e) {
            return [
                'status' => 'error',
                'error_type' => 'malformed',
                'message' => 'Unexpected runtime error while processing result.',
            ];
        } catch (\Throwable $e) {
            error_log("Unexpected error in ResultsController: " . $e->getMessage());
            error_log($e->getTraceAsString());
            return [
                'status' => 'error',
                'error_type' => 'unknown',
                'message' => 'An internal error occurred.',
            ];
        }
    }

    private function fetchResultRow(int $resultId): ?array
    {
        $DB  = new \Database();
        $pdo = $DB->getConnection();

        $sql = "
            SELECT
                o.opt_id, o.dataset_id, o.status, o.created_at, o.solver_status, o.solver_log,
                r.summary_json, r.detail_json, r.inputs_json,
                d.dataset_name
            FROM optimizations o
            LEFT JOIN results r ON r.opt_id = o.opt_id
            JOIN datasets d ON d.dataset_id = o.dataset_id
            WHERE o.opt_id = :opt_id
        ";

        $st = $pdo->prepare($sql);
        $st->execute(['opt_id' => $resultId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
