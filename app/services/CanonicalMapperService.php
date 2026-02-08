<?php

namespace App\Services;

class CanonicalMapperService
{
    private $inputsJson;
    private $detailJson;
    private $warnings = [];
    private $errors = [];

    public function __construct(array $inputsJson, array $detailJson)
    {
        $this->inputsJson = $inputsJson;
        $this->detailJson = $detailJson;
    }

    public function mapToCanonical(): ?array
    {
        $canonical = [];

        // 2. Global Parameters Mapping
        $canonical['global_parameters'] = $this->mapGlobalParameters();

        // 3. Vertical Distribution Mapping
        $canonical['vertical_distribution'] = $this->mapVerticalDistribution();

        // 4. Floors Construction Rule
        $floors = $this->buildFloors();

        // 5. Horizontal Distribution Mapping (Per Floor) and 6. Apartments Mapping
        $canonical['floors'] = $this->mapFloorsAndApartments($floors);

        if (!empty($this->errors)) {
            return null; // Or throw an exception, depending on desired error handling
        }

        return $canonical;
    }

    private function mapGlobalParameters(): array
    {
        return [
            'input_power_dbuv'     => $this->inputsJson['potencia_entrada'] ?? $this->addWarning('Missing potencia_entrada in inputs_json'),
            'target_tu_level_dbuv' => $this->inputsJson['Potencia_Objetivo_TU'] ?? $this->addWarning('Missing Potencia_Objetivo_TU in inputs_json'),
            'min_level_dbuv'       => $this->inputsJson['Nivel_minimo'] ?? $this->addWarning('Missing Nivel_minimo in inputs_json'),
            'max_level_dbuv'       => $this->inputsJson['Nivel_maximo'] ?? $this->addWarning('Missing Nivel_maximo in inputs_json'),
        ];
    }

    private function mapVerticalDistribution(): array
    {
        $totalAntennaTrunkCableLength = 0;
        $totalRiserBlockCableLength = 0;
        $totalAntennaTrunkConnectors = 0;
        $totalRiserTapsCount = 0;
        $uniqueVerticalRepartidores = []; // Store unique repartidor IDs

        foreach ($this->detailJson as $tu) {
            // Cable Lengths
            $totalAntennaTrunkCableLength += (float)($tu['Longitud Antena→Troncal (m)'] ?? 0);
            $totalRiserBlockCableLength   += (float)($tu['Distancia riser dentro bloque (m)'] ?? 0);

            // Antena-Troncal Connectors (2 if loss > 0)
            if (($tu['Pérdida Antena↔Troncal (conectores) (dB)'] ?? 0) > 0) {
                $totalAntennaTrunkConnectors += 2;
            }

            // Riser Taps (1 if loss > 0)
            if (($tu['Riser Atenuación Taps (dB)'] ?? 0) > 0) {
                $totalRiserTapsCount += 1;
            }

            // Vertical Splitters: Repartidor Troncal
            if (isset($tu['Repartidor Troncal']) && $tu['Repartidor Troncal'] > 0) {
                $repId = $tu['Repartidor Troncal'];
                if (!isset($uniqueVerticalRepartidores[$repId])) {
                    $uniqueVerticalRepartidores[$repId] = [
                        "splitter_model" => $this->inputsJson['repartidores_data'][$repId]['modelo'] ?? 'Unknown Repartidor',
                        "splitter_loss_db" => $this->inputsJson['repartidores_data'][$repId]['perdida_insercion'] ?? null, // Assuming perdida_insercion is the loss
                        // No floor_served for truly vertical splitters
                    ];
                }
            }
        }

        // Convert unique splitters back to a simple array
        $verticalSplitters = array_values($uniqueVerticalRepartidores);

        // Calculate total riser connectors from Riser Conectores (uds)
        $totalRiserConnectors = 0;
        foreach ($this->detailJson as $tu) {
            $totalRiserConnectors += (int)($tu['Riser Conectores (uds)'] ?? 0);
        }

        return [
            'total_antenna_trunk_cable_length_m' => $totalAntennaTrunkCableLength,
            'total_riser_block_cable_length_m'   => $totalRiserBlockCableLength,
            'total_antenna_trunk_connectors_count' => $totalAntennaTrunkConnectors,
            'total_riser_connectors_count'       => $totalRiserConnectors,
            'total_riser_taps_count'             => $totalRiserTapsCount,
            'vertical_splitters'                 => $verticalSplitters,
            'vertical_cable_loss_db_per_m'       => $this->inputsJson['atenuacion_cable_por_metro'] ?? null,
            'vertical_connector_loss_db'         => $this->inputsJson['atenuacion_conector'] ?? null,
        ];
    }

    private function buildFloors(): array
    {
        $floors = [];
        if (!is_array($this->detailJson)) {
            $this->addError('detail_json is not a valid array.');
            return [];
        }

        foreach ($this->detailJson as $tu) {
            if (isset($tu['Piso'])) {
                $floors[$tu['Piso']] = true;
            }
        }
        $uniqueFloors = array_keys($floors);
        sort($uniqueFloors);
        return $uniqueFloors;
    }

    private function mapFloorsAndApartments(array $uniqueFloors): array
    {
        $canonicalFloors = [];
        foreach ($uniqueFloors as $floorNumber) {
            $floorApartments = [];
            $floorTUs = array_filter($this->detailJson, fn($tu) => ($tu['Piso'] ?? null) == $floorNumber);

            if (empty($floorTUs)) {
                $this->addError("Floor $floorNumber has no apartments (TUs).");
                continue;
            }

            // For horizontal distribution, take the first TU as reference
            $referenceTU = reset($floorTUs);
            $horizontalDistribution = $this->mapHorizontalDistribution($referenceTU, $floorTUs, $floorNumber);

            $apartmentIndex = 1;
            foreach ($floorTUs as $tu) {
                $floorApartments[] = $this->mapApartment($tu, $floorNumber, $apartmentIndex++);
            }

            $canonicalFloors[] = [
                'floor_number'         => (int)$floorNumber,
                'horizontal_distribution' => $horizontalDistribution,
                'apartments'           => $floorApartments,
            ];
        }
        return $canonicalFloors;
    }

    private function mapHorizontalDistribution(array $referenceTU, array $allTUsOnFloor, int $floorNumber): array
    {
        // Validate all other TUs on same floor match
        foreach ($allTUsOnFloor as $tu) {
            // Check for consistency in 'Feeder Troncal→Entrada Bloque (m)' across TUs on the same floor
            if (($tu['Feeder Troncal→Entrada Bloque (m)'] ?? null) !== ($referenceTU['Feeder Troncal→Entrada Bloque (m)'] ?? null)) {
                $this->addWarning("Horizontal mismatch on floor $floorNumber: Feeder Troncal→Entrada Bloque (m) differs.");
                break;
            }
        }

        // Calculate total_floor_derivadores_count
        $totalFloorDerivadoresCount = 0;
        foreach ($allTUsOnFloor as $tu) {
            if (isset($tu['Derivador Piso']) && $tu['Derivador Piso'] > 0) {
                // If the Derivador Piso exists for this TU, count it.
                // The original InventoryAggregator counted 1 Derivador per TU if present.
                $totalFloorDerivadoresCount += 1;
            }
        }

        return [
            'horizontal_cable_length_m'      => ($referenceTU['Feeder Troncal→Entrada Bloque (m)'] ?? 0) * ($this->inputsJson['apartamentos_por_piso'] ?? 1),
            'horizontal_cable_loss_db_per_m' => $this->inputsJson['atenuacion_cable_por_metro'] ?? $this->addWarning("Missing atenuacion_cable_por_metro in inputs_json for floor $floorNumber"),
            'horizontal_connectors_count'    => ($this->inputsJson['apartamentos_por_piso'] ?? 0) * 2, // Derived based on 2 connectors per apartment feeder run
            'horizontal_connector_loss_db'   => $this->inputsJson['atenuacion_conector'] ?? $this->addWarning("Missing atenuacion_conector in inputs_json for floor $floorNumber"),
            'total_floor_derivadores_count'  => $totalFloorDerivadoresCount,
        ];
    }

    private function mapApartment(array $tu, int $floorNumber, int $apartmentIndex): array
    {
        if (empty($tu)) {
            $this->addError("Apartment without TU data on floor $floorNumber.");
            return [];
        }

        $tomas = [];
        // Assuming one Toma per apartment for now, mapping 'Pérdida Conexión TU (dB)' to toma_loss_db
        $tomas[] = [
            'toma_id' => "T1", // As per spec, "T1"
            'toma_loss_db' => $tu['Pérdida Conexión TU (dB)'] ?? $this->addWarning("Missing Pérdida Conexión TU (dB) for apartment F{$floorNumber}_A{$apartmentIndex}"),
        ];

        // Derived apartment cable length
        $totalDistanceToTu = (float)($tu['Distancia total hasta la toma (m)'] ?? 0);
        $lengthAntenaTroncal = (float)($tu['Longitud Antena→Troncal (m)'] ?? 0);
        $lengthRiserBloque = (float)($tu['Distancia riser dentro bloque (m)'] ?? 0);
        $lengthFeeder = (float)($tu['Feeder Troncal→Entrada Bloque (m)'] ?? 0);
        $nonApartmentCableLength = $lengthAntenaTroncal + $lengthRiserBloque + $lengthFeeder;
        $calculatedApartmentCableLength = max(0, $totalDistanceToTu - $nonApartmentCableLength);

        // Separate connector counts
        $derivRepConnectorsCount = 0;
        if (($tu['Pérdida Cable Deriv→Rep (dB)'] ?? 0) > 0) {
            $derivRepConnectorsCount = 2;
        }

        $repTuConnectorsCount = 0;
        if (($tu['Pérdida Cable Rep→TU (dB)'] ?? 0) > 0) {
            $repTuConnectorsCount = 2;
        }

        $conexionTuConnectorsCount = 0;
        if (($tu['Pérdida Conexión TU (dB)'] ?? 0) > 0) {
            $conexionTuConnectorsCount = 1;
        }

        return [
            'apartment_id'      => "F{$floorNumber}_A{$apartmentIndex}",
            'apartment_internals' => [
                'calculated_apartment_cable_length_m' => $calculatedApartmentCableLength,
                'apartment_cable_loss_db_per_m' => $this->inputsJson['atenuacion_cable_por_metro'] ?? $this->addWarning("Missing atenuacion_cable_por_metro in inputs_json for apartment F{$floorNumber}_A{$apartmentIndex}"),
                'deriv_rep_connectors_count'  => $derivRepConnectorsCount,
                'rep_tu_connectors_count'     => $repTuConnectorsCount,
                'conexion_tu_connectors_count' => $conexionTuConnectorsCount,
                'apartment_connector_loss_db' => $this->inputsJson['atenuacion_conexion_tu'] ?? $this->addWarning("Missing atenuacion_conexion_tu in inputs_json for apartment F{$floorNumber}_A{$apartmentIndex}"),
                'tomas'                       => $tomas,
            ],
            // 'Nivel_TU_Final' is explicitly not stored here
        ];
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    private function addError(string $message)
    {
        $this->errors[] = $message;
        return null; // Return null to allow null-coalescing operator to work
    }

    private function addWarning(string $message)
    {
        $this->warnings[] = $message;
    }
}
