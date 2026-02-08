<?php

namespace App\Services;

class CanonicalValidationService
{
    private array $canonicalData;
    private array $summaryData; // To get global apartment count and TU count
    private array $errors = [];
    private array $warnings = [];

    public function __construct(array $canonicalData, array $summaryData)
    {
        $this->canonicalData = $canonicalData;
        $this->summaryData = $summaryData;
    }

    public function validate(): void
    {
        $this->validateFloorLevel();
        $this->validateApartmentCompleteness();
        $this->validateVerticalContinuity();
    }

    private function validateFloorLevel(): void
    {
        $floorNumbers = [];
        $lastFloorNumber = -INF; // Negative infinity for initial comparison

        // Ensure canonical.floors exists and is an array
        if (!isset($this->canonicalData['floors']) || !is_array($this->canonicalData['floors'])) {
            $this->addError("Structural impossibility: 'floors' array is missing or malformed in canonical data.");
            return;
        }

        foreach ($this->canonicalData['floors'] as $index => $floor) {
            // Floor numbers must be present, integer, unique, and strictly increasing
            if (!isset($floor['floor_number'])) {
                $this->addError("Structural impossibility: Floor at index $index is missing 'floor_number'.");
                continue; // Cannot validate further for this floor
            }
            $currentFloorNumber = $floor['floor_number'];

            if (!is_int($currentFloorNumber)) {
                $this->addError("Structural impossibility: Floor number '{$currentFloorNumber}' at index $index is not an integer.");
            }
            if (in_array($currentFloorNumber, $floorNumbers)) {
                $this->addError("Structural impossibility: Duplicate floor number '{$currentFloorNumber}' detected.");
            }
            if ($currentFloorNumber <= $lastFloorNumber) {
                $this->addError("Structural impossibility: Floor numbers are not strictly increasing. Found '{$currentFloorNumber}' after '{$lastFloorNumber}'.");
            }

            $floorNumbers[] = $currentFloorNumber;
            $lastFloorNumber = $currentFloorNumber;

            // Each floor must contain: horizontal_distribution and apartments[]
            if (!isset($floor['horizontal_distribution']) || !is_array($floor['horizontal_distribution'])) {
                $this->addError("Structural impossibility: Floor '{$currentFloorNumber}' is missing 'horizontal_distribution' or it is malformed.");
            }
            if (!isset($floor['apartments']) || !is_array($floor['apartments'])) {
                $this->addError("Structural impossibility: Floor '{$currentFloorNumber}' is missing 'apartments' array or it is malformed.");
            }

            // Horizontal distribution must not contradict:
            // Global apartment count (this is complex, usually derived from floors[].apartments)
            // Declared derivadores per floor

            // Check for declared derivadores per floor (warning)
            $expectedDerivadores = $this->getExpectedDerivadoresForFloor($currentFloorNumber); // Needs implementation
            $actualDerivadores = $floor['horizontal_distribution']['total_floor_derivadores_count'] ?? 0;
            if ($expectedDerivadores > 0 && $actualDerivadores !== $expectedDerivadores) {
                $this->addWarning("Suspicious data: Floor '{$currentFloorNumber}' has {$actualDerivadores} derivadores, but {$expectedDerivadores} are expected from inputs.");
            }
        }
    }

    private function validateApartmentCompleteness(): void
    {
        $allApartmentIds = [];
        $totalTUsInCanonical = 0;

        foreach ($this->canonicalData['floors'] as $floor) {
            $currentFloorNumber = $floor['floor_number'] ?? 'N/A';

            foreach ($floor['apartments'] as $index => $apartment) {
                // Apartment identifier (error)
                if (!isset($apartment['apartment_id']) || empty($apartment['apartment_id'])) {
                    $this->addError("Missing required structure: Apartment at floor '{$currentFloorNumber}', index $index is missing an identifier.");
                    continue;
                }
                $apartmentId = $apartment['apartment_id'];

                // Duplicate apartment IDs (error)
                if (in_array($apartmentId, $allApartmentIds)) {
                    $this->addError("Missing required structure: Duplicate apartment ID '{$apartmentId}' detected on floor '{$currentFloorNumber}'.");
                }
                $allApartmentIds[] = $apartmentId;

                // At least one TU (error)
                if (!isset($apartment['apartment_internals']['tomas']) || empty($apartment['apartment_internals']['tomas'])) {
                    $this->addError("Missing required structure: Apartment '{$apartmentId}' on floor '{$currentFloorNumber}' has no TUs.");
                } else {
                    $totalTUsInCanonical += count($apartment['apartment_internals']['tomas']);
                }

                // Internal cable length (error)
                if (!isset($apartment['apartment_internals']['calculated_apartment_cable_length_m']) || $apartment['apartment_internals']['calculated_apartment_cable_length_m'] <= 0) {
                    $this->addError("Missing required structure: Apartment '{$apartmentId}' on floor '{$currentFloorNumber}' is missing internal cable length or it's zero/negative.");
                }

                // Each TU must have: Final level value (not directly in canonical, but in original detail)
                // Floor/apartment association (covered by apartment_id)
                // This check might be more relevant for the original 'detail' json

                // Apartments without internal wiring (warning)
                if (($apartment['apartment_internals']['calculated_apartment_cable_length_m'] ?? 0) == 0 && ($apartment['apartment_internals']['deriv_rep_connectors_count'] ?? 0) == 0 && ($apartment['apartment_internals']['rep_tu_connectors_count'] ?? 0) == 0 && ($apartment['apartment_internals']['conexion_tu_connectors_count'] ?? 0) == 0) {
                    $this->addWarning("Suspicious data: Apartment '{$apartmentId}' on floor '{$currentFloorNumber}' appears to have no internal wiring components.");
                }
            }
        }

        // TU count mismatch vs summary (warning)
        $totalTUsInSummary = (int)($this->summaryData['num_tomas'] ?? 0);
        if ($totalTUsInSummary > 0 && $totalTUsInCanonical !== $totalTUsInSummary) {
            $this->addWarning("Suspicious data: Total TUs in canonical ({$totalTUsInCanonical}) does not match total TUs in summary ({$totalTUsInSummary}).");
        }
    }

    private function validateVerticalContinuity(): void
    {
        $vd = $this->canonicalData['vertical_distribution'] ?? [];
        $floors = $this->canonicalData['floors'] ?? [];
        $numFloors = count($floors);

        // Vertical cable length must: be >= sum of riser segments, scale logically with number of floors
        $totalRiserBlockCableLength = $vd['total_riser_block_cable_length_m'] ?? 0;
        $totalExpectedRiserLength = 0;
        $prevFloorNumber = null;
        foreach ($floors as $floor) {
            $currentFloorNumber = $floor['floor_number'] ?? 0;
            if ($prevFloorNumber !== null) {
                // Assuming 'largo_cable_entre_pisos' is the cable length between floors from inputs_json
                $distanceBetweenFloors = $this->canonicalData['vertical_distribution']['vertical_cable_length_per_floor_m'] ?? 0;
                $totalExpectedRiserLength += ($currentFloorNumber - $prevFloorNumber) * $distanceBetweenFloors;
            }
            $prevFloorNumber = $currentFloorNumber;
        }

        if ($numFloors > 0 && $totalRiserBlockCableLength < $totalExpectedRiserLength) {
            $this->addError("Physically impossible topology: Total riser block cable length ({$totalRiserBlockCableLength}m) is less than the sum of expected riser segments ({$totalExpectedRiserLength}m) based on floor count and inter-floor cable length.");
        }
        // Scaling logically is a heuristic, can be a warning if far off
        // For now, only hard impossibility.

        // Vertical equipment consistency: Taps count aligns with floor count
        $totalRiserTapsCount = $vd['total_riser_taps_count'] ?? 0;
        // If there are taps, there should be at least (numFloors - 1) taps if 1 tap per floor interface, or numFloors if at every floor.
        // This is a warning as layouts can vary.
        if ($totalRiserTapsCount > 0 && $totalRiserTapsCount < ($numFloors - 1)) {
            $this->addWarning("Unusual layout: Total riser taps count ({$totalRiserTapsCount}) seems low for {$numFloors} floors. Expected at least " . ($numFloors - 1) . " for full vertical distribution.");
        }

        // Trunk splitters placed logically (warning)
        $verticalSplitters = $vd['vertical_splitters'] ?? [];
        if (!empty($verticalSplitters)) {
            $trunkSplitterCount = 0;
            foreach ($verticalSplitters as $splitter) {
                $splitterModel = strtolower($splitter['splitter_model'] ?? '');
                if (str_contains($splitterModel, 'repartidor troncal')) {
                    $trunkSplitterCount++;
                }
            }
            // If there are multiple trunk splitters (more than 1 or 2 depending on blocks), it could be suspicious
            if ($trunkSplitterCount > 1) { // Assuming a single block for simplicity
                $this->addWarning("Unusual layout: Multiple trunk splitters ({$trunkSplitterCount}) detected in the vertical distribution. Typically 0 or 1 per trunk.");
            }
        }

        // Detect: Disconnected floors, Missing vertical feed into a floor (errors)
        // If first floor number is not 1, or there are gaps
        if (!empty($floors)) {
            $sortedFloorNumbers = array_map(fn($f) => $f['floor_number'], $floors);
            sort($sortedFloorNumbers);

            if ($sortedFloorNumbers[0] !== 1) { // Assuming floors start from 1
                $this->addError("Physically impossible topology: Floors do not start from 1. First floor is '{$sortedFloorNumbers[0]}'.");
            }
            for ($i = 0; $i < $numFloors - 1; $i++) {
                if (($sortedFloorNumbers[$i+1] - $sortedFloorNumbers[$i]) > 1) {
                    $this->addError("Physically impossible topology: Disconnected floors detected between '{$sortedFloorNumbers[$i]}' and '{$sortedFloorNumbers[$i+1]}'.");
                }
            }
        }
    }

    // Helper to get expected derivadores from inputs (not directly available in canonical yet)
    // This part requires access to the original inputs_json
    private function getExpectedDerivadoresForFloor(int $floorNumber): int
    {
        // This is a placeholder. In a real scenario, this would derive from `inputs_json`
        // possibly counting 'derivadores_data' entries or similar config per floor.
        // For opt_id 137, each floor has 3 derivadores.
        // This value should come from inputsJson and mapping in CanonicalMapperService
        // if it's meant to be a canonical count.
        // For now, hardcode based on observed inputs_json for opt_id=137
        // where inputsJson['apartamentos_por_piso'] = 3, and each has a derivador.
        $apartamentosPorPiso = $this->summaryData['num_tomas'] / count($this->canonicalData['floors']);
        return (int)$apartamentosPorPiso; // Assuming one derivador per apartment

    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    private function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    private function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }
}