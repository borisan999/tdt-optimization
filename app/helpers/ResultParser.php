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
        if (!isset($row['detail_json'])) {
            throw new \DomainException('Missing detail_json column.');
        }

        if (!is_string($row['detail_json']) || trim($row['detail_json']) === '') {
            throw new \DomainException('Invalid detail_json payload.');
        }

        $decoded = json_decode($row['detail_json'], true);

        if (!is_array($decoded)) {
            throw new \DomainException('Malformed JSON in detail_json.');
        }

        $row['__decoded_detail'] = $decoded;

        return new self($row);
    }

    private function __construct(array $row)
    {
        $this->row = $row;

        $this->detail = $row['__decoded_detail'];
        $this->summary = $this->decodeJson($row['summary_json'] ?? null, 'summary_json');
        $this->inputs = $this->decodeJson($row['inputs_json'] ?? null, 'inputs_json');
    }

    private function decodeJson(?string $json, string $field): array
    {
        if (!$json) {
            throw new \DomainException("Result {$field} is empty");
        }

        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            throw new \DomainException("Result {$field} malformed JSON");
        }

        return $decoded;
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
        if (!is_array($this->detail)) {
            throw new \DomainException('Canonical detail must be an array.');
        }

        $normalizedDetail = [];
        $numericKeys = ['piso','apto','bloque','nivel_tu','nivel_min','nivel_max'];

        foreach ($this->detail as $index => $tu) {

            if (!is_array($tu)) {
                throw new \DomainException("TU at index {$index} must be an array.");
            }

            $requiredKeys = [
                'tu_id','piso','apto','bloque',
                'nivel_tu','nivel_min','nivel_max',
                'cumple','losses'
            ];

            foreach ($requiredKeys as $key) {
                if (!array_key_exists($key, $tu)) {
                    throw new \DomainException("Missing '{$key}' in TU at index {$index}.");
                }
            }

            if (!is_string($tu['tu_id']) || trim($tu['tu_id']) === '') {
                throw new \DomainException("Invalid tu_id at index {$index}.");
            }

            foreach ($numericKeys as $key) {
                if (!is_numeric($tu[$key])) {
                    throw new \DomainException("Invalid numeric '{$key}' at index {$index}.");
                }
            }

            if (!is_bool($tu['cumple']) && $tu['cumple'] !== 0 && $tu['cumple'] !== 1) {
                throw new \DomainException("Invalid cumple at index {$index}.");
            }

            if (!is_array($tu['losses'])) {
                throw new \DomainException("Invalid losses at index {$index}.");
            }

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

        if (!is_array($this->summary)) {
            throw new \DomainException('Canonical summary must be an array.');
        }

        if (!is_array($this->inputs)) {
            throw new \DomainException('Canonical inputs must be an array.');
        }

        return [
            'detail'  => $normalizedDetail,
            'summary' => $this->summary,
            'inputs'  => $this->inputs,
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
