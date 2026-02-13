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
            // This allows for valid results with 0 TUs, returning an empty detail array.
            return;
        }

        $normalizedDetail = [];
        $numericKeys = ['piso', 'apto', 'bloque', 'nivel_tu', 'nivel_min', 'nivel_max'];

        foreach ($this->detail as $index => $tu) {
            if (!is_array($tu)) {
                throw new RuntimeException("Canonical violation: TU at index {$index} is not an object.");
            }

            // 1. Presence Checks
            $requiredKeys = ['tu_id', 'piso', 'apto', 'bloque', 'nivel_tu', 'nivel_min', 'nivel_max', 'cumple', 'losses'];
            foreach ($requiredKeys as $key) {
                if (!array_key_exists($key, $tu)) {
                    throw new RuntimeException("Canonical violation: missing '{$key}' in TU at index {$index}.");
                }
            }

            // 2. Type Compatibility Checks
            if (!is_string($tu['tu_id']) || empty(trim($tu['tu_id']))) {
                throw new RuntimeException("Canonical violation: 'tu_id' must be a non-empty string in TU at index {$index}.");
            }
            foreach ($numericKeys as $key) {
                if (!is_numeric($tu[$key])) {
                    throw new RuntimeException("Canonical violation: '{$key}' must be numeric in TU at index {$index}.");
                }
            }
            if (!is_bool($tu['cumple'])) {
                // Allow 0 or 1 from JSON, which are not strictly booleans but are bool-compatible
                if ($tu['cumple'] !== 0 && $tu['cumple'] !== 1) {
                     throw new RuntimeException("Canonical violation: 'cumple' must be a boolean (or 0/1) in TU at index {$index}.");
                }
            }
            if (!is_array($tu['losses'])) {
                throw new RuntimeException("Canonical violation: 'losses' must be an array in TU at index {$index}.");
            }

            // 3. Normalize and build the new array
            $normalizedDetail[] = [
                'tu_id'     => (string)$tu['tu_id'],
                'piso'      => (int)$tu['piso'],
                'apto'      => (int)$tu['apto'],
                'bloque'    => (int)$tu['bloque'],
                'nivel_tu'  => (float)$tu['nivel_tu'],
                'nivel_min' => (float)$tu['nivel_min'],
                'nivel_max' => (float)$tu['nivel_max'],
                'cumple'    => (bool)$tu['cumple'],
                'losses'    => $tu['losses'],
            ];
        }

        // Overwrite the original detail array with the fully validated and normalized version.
        $this->detail = $normalizedDetail;
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
