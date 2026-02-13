<?php

namespace app\helpers;

use RuntimeException;
use DateTime;

class ResultParser
{
    private array $row;
    private array $detail;
    private array $summary;
    private array $inputs; // Added private property

    public static function fromDbRow(array $row): self
    {
        return new self($row);
    }

    private function __construct(array $row)
    {
        $this->row = $row;

        $this->detail = $this->decodeJson($row['detail_json'] ?? null, 'detail_json');
        $this->summary = $this->decodeJson($row['summary_json'] ?? null, 'summary_json');
        $this->inputs = $this->decodeJson($row['inputs_json'] ?? null, 'inputs_json'); // Initialize inputs

        $this->validateCanonical();
    }

    private function decodeJson(?string $json, string $field): array
    {
        if (!$json) {
            throw new RuntimeException("Result {$field} is empty");
        }

        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            throw new RuntimeException("Result {$field} malformed JSON");
        }

        return $decoded;
    }

    private function validateCanonical(): void
    {
        if (empty($this->detail)) {
            throw new RuntimeException("Canonical detail_json is empty");
        }

        foreach ($this->detail as $index => $tu) {

            if (!is_array($tu)) {
                throw new RuntimeException("TU index {$index} is not an object");
            }

            $requiredKeys = [
                'tu_id',
                'piso',
                'apto',
                'bloque',
                'nivel_tu',
                'nivel_min',
                'nivel_max',
                'cumple',
                'losses'
            ];

            foreach ($requiredKeys as $key) {
                if (!array_key_exists($key, $tu)) {
                    throw new RuntimeException(
                        "Canonical violation: missing '{$key}' in TU index {$index}"
                    );
                }
            }

            if (!is_array($tu['losses'])) {
                throw new RuntimeException(
                    "Canonical violation: losses must be array (TU index {$index})"
                );
            }
        }
    }

    /* ===============================
       Public API (STRICT)
       =============================== */

    public function meta(): array
    {
        return [
            'opt_id'     => $this->row['opt_id'] ?? null,
            'status'     => $this->row['status'] ?? null,
            'dataset_id' => $this->row['dataset_id'] ?? null,
            'created_at' => isset($this->row['created_at'])
                ? new DateTime($this->row['created_at'])
                : null,
        ];
    }

    public function canonical(): array
    {
        return [
            'detail'  => $this->detail,
            'summary' => $this->summary,
            'inputs'  => $this->inputs, // Return inputs property
        ];
    }

    public function summary(): array
    {
        return $this->summary;
    }

    public function warnings(): array
    {
        return []; // No legacy, no warnings
    }

    public function errors(): array
    {
        return []; // Strict mode throws immediately
    }

    public function hasErrors(): bool
    {
        return false;
    }
}
