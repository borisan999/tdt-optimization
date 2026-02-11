<?php

namespace App\Services;

class CanonicalRehydrationService
{
    private array $inputs;
    private array $details;

    private array $errors = [];
    private array $warnings = [];

    public function __construct(array $inputs, array $details)
    {
        $this->inputs = $inputs;
        $this->details = $details;
    }

    public function mapToCanonical(): ?array
    {
        $canonical = [
            'inputs' => $this->normalizeInputs(),
            'apartments' => [],
            'tus' => [],
        ];

        $this->normalizeApartmentsAndTus($canonical);

        if (!empty($this->errors)) {
            return null;
        }

        return $canonical;
    }

    /* =====================================================
       INPUT NORMALIZATION
       ===================================================== */

    private function normalizeInputs(): array
    {
        if (empty($this->inputs)) {
            $this->warnings[] = "Inputs array is empty.";
            return [];
        }

        return $this->inputs['parameters'] ?? $this->inputs;
    }

    /* =====================================================
       APARTMENT + TU NORMALIZATION
       ===================================================== */

    private function normalizeApartmentsAndTus(array &$canonical): void
    {
        if (empty($this->details)) {
            $this->errors[] = "Details data missing.";
            return;
        }

        // Case 1 — Proper tu_results structure
        if (isset($this->details['tu_results'])) {
            $this->mapFromTuResults($canonical);
            return;
        }

        // Case 2 — Legacy flat array
        if (isset($this->details[0])) {
            $this->warnings[] = "Legacy flat details detected. Normalizing.";
            $this->mapFromFlatArray($canonical);
            return;
        }

        $this->errors[] = "Unrecognized details structure.";
    }

    /* =====================================================
       STANDARD STRUCTURE
       ===================================================== */

    private function mapFromTuResults(array &$canonical): void
    {
        $tuResults = $this->details['tu_results'];

        if (!is_array($tuResults)) {
            $this->errors[] = "tu_results is not an array.";
            return;
        }

        $apartmentIndex = [];

        foreach ($tuResults as $row) {

            $floor = (int)($row['floor'] ?? -1);
            $apt   = (int)($row['apartment'] ?? -1);

            if ($floor < 0 || $apt < 0) {
                $this->warnings[] = "Invalid floor/apartment detected.";
                continue;
            }

            $aptKey = "$floor-$apt";

            // Build apartment if not already created
            if (!isset($apartmentIndex[$aptKey])) {

                $apartmentIndex[$aptKey] = true;

                $canonical['apartments'][] = [
                    'floor' => $floor,
                    'apartment' => $apt,
                    'tus_required' => (int)($row['tus_required'] ?? 0),
                    'cable_derivador' => (float)($row['cable_derivador'] ?? 0),
                    'cable_repartidor' => (float)($row['cable_repartidor'] ?? 0),
                ];
            }

            // Handle TU
            $canonical['tus'][] = [
                'floor' => $floor,
                'apartment' => $apt,
                'tu_index' => (int)($row['tu_index'] ?? 0),
                'cable_length' => (float)($row['cable_length'] ?? 0),
            ];
        }

        if (empty($canonical['apartments'])) {
            $this->warnings[] = "No apartments mapped.";
        }

        if (empty($canonical['tus'])) {
            $this->warnings[] = "No TUs mapped.";
        }
    }

    /* =====================================================
       LEGACY FLAT ARRAY
       ===================================================== */

    private function mapFromFlatArray(array &$canonical): void
    {
        $normalizedRowsCount = 0;
        $skippedRowsCount = 0;
        $nestedTusCount = 0;

        foreach ($this->details as $row) {
            if (!isset($row['floor'], $row['apartment'])) {
                $skippedRowsCount++;
                continue;
            }

            $normalizedRowsCount++;
            $floor = (int)$row['floor'];
            $apt   = (int)$row['apartment'];

            $canonical['apartments'][] = [
                'floor' => $floor,
                'apartment' => $apt,
                'tus_required' => (int)($row['tus_required'] ?? 0),
                'cable_derivador' => (float)($row['cable_derivador'] ?? 0),
                'cable_repartidor' => (float)($row['cable_repartidor'] ?? 0),
            ];

            if (isset($row['tus']) && is_array($row['tus'])) {
                $nestedTusCount++;
                foreach ($row['tus'] as $index => $tu) {
                    $canonical['tus'][] = [
                        'floor' => $floor,
                        'apartment' => $apt,
                        'tu_index' => $index + 1,
                        'cable_length' => (float)($tu['cable_length'] ?? 0),
                    ];
                }
            }
        }

        if ($normalizedRowsCount || $skippedRowsCount || $nestedTusCount) {
            $this->warnings[] =
                "Legacy normalization summary: " .
                "normalized={$normalizedRowsCount}, " .
                "skipped={$skippedRowsCount}, " .
                "flattened_nested_tus={$nestedTusCount}.";
        }
    }

    /* =====================================================
       ACCESSORS
       ===================================================== */

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }
}
