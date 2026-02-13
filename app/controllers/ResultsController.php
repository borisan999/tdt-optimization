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
    private int $opt_id;

    public function __construct(int $opt_id)
    {
        $this->opt_id = $opt_id;
    }

    public function execute(): array
    {
        try {

            if ($this->opt_id <= 0) {
                return $this->error('malformed', 'Invalid optimization identifier.');
            }

            $row = $this->fetchRow();

            if (!$row) {
                return $this->error('not_found', 'Optimization not found.');
            }

            if (empty($row['detail_json'])) {
                return $this->error('no_results', 'Optimization has not produced results yet.');
            }

            $parser = ResultParser::fromDbRow($row);
            $canonical = $parser->canonical();

            // 🔴 STRICT structural validation
            if (
                !isset($canonical['summary'], $canonical['detail']) ||
                !is_array($canonical['summary']) ||
                !is_array($canonical['detail'])
            ) {
                throw new \DomainException('Invalid canonical structure.');
            }

            // Optional but Strongly Recommended: Validate each TU structure before compliance calculation
            foreach ($canonical['detail'] as $tu) {
                if (!is_array($tu)) {
                    throw new \DomainException('Invalid TU structure.');
                }
            }

            $violations = ResultComplianceService::calculateViolations($canonical['detail']);

            $meta = $parser->meta();
            if (isset($meta['created_at']) && $meta['created_at'] instanceof \DateTimeInterface) {
                $meta['created_at'] = $meta['created_at']->format('Y-m-d H:i:s');
            }

            return [
                'status' => 'success',
                'meta' => $meta,
                'summary' => $canonical['summary'],
                'details' => $canonical['detail'],
                'inputs' => $canonical['inputs'],
                'violations' => $violations,
                'warnings' => $parser->warnings(),
            ];

        } catch (\DomainException $e) {

            error_log('[RESULT_DOMAIN_ERROR] ' . $e->getMessage());
            return $this->error('malformed', 'Result data is invalid.');

        } catch (\RuntimeException $e) {

            error_log('[RESULT_RUNTIME_ERROR] ' . $e->getMessage());
            return $this->error('parser_error', 'An internal processing error occurred.');

        } catch (\Throwable $e) {

            error_log('[RESULT_FATAL] ' . $e->getMessage());
            return $this->error('unexpected', 'Unexpected system error.');
        }
    }

    private function fetchRow(): ?array
    {
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

        return $row ?: null;
    }

    private function error(string $type, string $message): array
    {
        return [
            'status' => 'error',
            'error_type' => $type,
            'message' => $message,
        ];
    }
}
