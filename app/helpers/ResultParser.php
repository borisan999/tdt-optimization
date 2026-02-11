<?php
namespace app\helpers;

require_once __DIR__ . '/../services/CanonicalRehydrationService.php';
// REMOVE: require_once __DIR__ . '/../services/CanonicalValidationService.php';
require_once __DIR__ . '/../services/CanonicalSchemaValidator.php';

use App\Services\CanonicalRehydrationService;
// REMOVE: use App\Services\CanonicalValidationService;
use App\Services\CanonicalSchemaValidator;
use DateTime;
use Throwable;

class ResultParser
{
    private array $errors = [];
    private array $warnings = [];

    private array $meta = [];
    private array $canonical = [];

    private function __construct() {}

    public static function fromDbRow(array $row): self
    {
        $parser = new self();

        $parser->parseMeta($row);

        // --- Always decode ---
        $inputs  = $parser->decodeJson($row['inputs_json'] ?? null, 'inputs_json');
        $details = $parser->decodeJson($row['detail_json'] ?? null, 'detail_json');
        $summary = $parser->decodeJson($row['summary_json'] ?? null, 'summary_json');

        // --- Always map ---
        $mapper = new CanonicalRehydrationService($inputs, $details);
        $canonicalData = $mapper->mapToCanonical();

        if (is_array($canonicalData)) {
            $parser->canonical = $canonicalData;
        } else {
            $parser->errors[] = 'Canonical mapping failed.';
        }

        // --- Validate canonical schema ---
        $schemaValidator = new CanonicalSchemaValidator();
        if (!$schemaValidator->validate($parser->canonical)) {
            throw new \RuntimeException(
                "Canonical schema validation failed: " .
                implode("; ", $schemaValidator->getErrors())
            );
        }

        $parser->errors   = array_merge($parser->errors, $mapper->getErrors());
        $parser->warnings = array_merge($parser->warnings, $mapper->getWarnings());

        // --- Validate canonical ---
        // REMOVE this entire block:
        // if (!empty($parser->canonical)) {
        //     $validator = new CanonicalValidationService($parser->canonical, $summary);
        //     $validator->validate();
        //
        //     $parser->errors   = array_merge($parser->errors, $validator->getErrors());
        //     $parser->warnings = array_merge($parser->warnings, $validator->getWarnings());
        // }

        return $parser;
    }

    private function decodeJson(?string $json, string $field): array
    {
        if ($json === null || $json === '') {
            $this->errors[] = "$field is empty";
            return [];
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (Throwable $t) {
            $this->errors[] = "$field invalid JSON: " . $t->getMessage();
            error_log("ResultParser::$field JSON error: " . $t->getMessage());
            return [];
        }
    }

    private function parseMeta(array $row): void
    {
        $start = isset($row['start_time']) ? new DateTime($row['start_time']) : null;
        $end   = isset($row['end_time']) ? new DateTime($row['end_time']) : null;

        $runtime = null;
        if ($start && $end) {
            $runtime = $end->getTimestamp() - $start->getTimestamp();
        }

        $this->meta = [
            'opt_id' => (int)($row['opt_id'] ?? 0),
            'status' => $row['status'] ?? 'unknown',
            'start_time' => $start,
            'end_time' => $end,
            'runtime_seconds' => $runtime,
        ];
    }

    /* =====================
       PUBLIC API
       ===================== */

    public function meta(): array
    {
        return $this->meta;
    }

    public function canonical(): array
    {
        return $this->canonical;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function warnings(): array
    {
        return $this->warnings;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }
}
