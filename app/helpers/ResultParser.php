<?php
namespace app\helpers;

use DateTime;
use Throwable;

class ResultParser
{
    private array $errors = [];

    private array $meta = [];
    private array $summary = [];
    private array $details = [];
    private array $inputs = [];

    private function __construct() {}

    /**
     * Factory: build parser from a single DB row
     */
    public static function fromDbRow(array $row): self
    {
        $parser = new self();

        $parser->parseMeta($row);
        $parser->summary = $parser->decodeJson($row['summary_json'] ?? null, 'summary_json');
        $parser->details = $parser->decodeJson($row['detail_json'] ?? null, 'detail_json');
        $parser->inputs  = $parser->decodeJson($row['inputs_json'] ?? null, 'inputs_json');

        return $parser;
    }

    /**
     * Decode JSON safely
     */
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

    /**
     * Extract non-JSON metadata
     */
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
       Public Accessors
       ===================== */

    public function meta(): array
    {
        return $this->meta;
    }

    public function summary(): array
    {
        return $this->summary;
    }

    public function details(): array
    {
        return $this->details;
    }

    public function inputs(): array
    {
        return $this->inputs;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /* =====================
       Convenience Helpers
       ===================== */

    public function objectiveValue()
    {
        return $this->summary['objective']['value'] ?? null;
    }

    public function kpis(): array
    {
        return $this->summary['kpis'] ?? [];
    }

    public function tuResults(): array
    {
        return $this->details['tu_results'] ?? [];
    }

    public function parameters(): array
    {
        return $this->inputs['parameters'] ?? [];
    }
}
