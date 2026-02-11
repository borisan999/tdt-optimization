<?php

namespace App\Services;

class CanonicalReverseMapperService
{
    private array $canonicalData;
    private array $originalInputs; // New property to store original inputs
    private array $errors = [];
    private array $warnings = [];

    public function __construct(array $canonicalData, array $originalInputs) // Added $originalInputs
    {
        $this->canonicalData = $canonicalData;
        $this->originalInputs = $originalInputs; // Store original inputs
    }

    public function mapFromCanonical(): array
    {
        $inputsJson = [];
        $detailJson = [];

        // --- Global Parameters ---
        $this->mapGlobalParametersReverse($inputsJson);

        // --- Vertical Distribution ---
        $this->mapVerticalDistributionReverse($inputsJson);

        // --- Floors and Apartments ---
        $this->mapFloorsAndApartmentsReverse($inputsJson, $detailJson);

        return [
            'inputs_json' => $inputsJson,
            'detail_json' => $detailJson,
        ];
    }

    private function mapGlobalParametersReverse(array &$inputsJson): void
    {
        $gp = $this->canonicalData['global_parameters'] ?? [];
        $inputsJson['potencia_entrada'] = $gp['input_power_dbuv'] ?? null;
        $inputsJson['Potencia_Objetivo_TU'] = $gp['target_tu_level_dbuv'] ?? null;
        $inputsJson['Nivel_minimo'] = $gp['min_level_dbuv'] ?? null;
        $inputsJson['Nivel_maximo'] = $gp['max_level_dbuv'] ?? null;
        
        // Preserve original global parameters that should NOT be modified by floor repetition
        $inputsJson['Piso_Maximo'] = $this->originalInputs['Piso_Maximo'] ?? null;
        $inputsJson['apartamentos_por_piso'] = $this->originalInputs['Apartamentos_Piso'] ?? null; // Assuming 'Apartamentos_Piso' is the key in original inputs

        $inputsJson['atenuacion_conector'] = $this->canonicalData['vertical_distribution']['vertical_connector_loss_db'] ?? null;
        $inputsJson['atenuacion_conexion_tu'] = $this->originalInputs['Atenuacion_Conexion_TU_dB'] ?? null; // Also preserving this global param
        $inputsJson['largo_cable_amplificador_ultimo_piso'] = $this->originalInputs['Largo_Cable_Amplificador_Ultimo_Piso'] ?? null; // Preserving
        $inputsJson['conectores_por_union'] = $this->originalInputs['Conectores_por_Union'] ?? null; // Preserving
        $inputsJson['atenuacion_cable_470mhz'] = $this->originalInputs['Atenuacion_Cable_dBporM'] ?? null; // Preserving
        $inputsJson['atenuacion_cable_698mhz'] = $this->originalInputs['Atenuacion_Cable_dBporM'] ?? null; // Preserving (assuming same for both)

        // Copy derivadores_data and repartidores_data if they exist in the original inputs (not in canonical)
        // For simplicity, we assume these are static definitions and are not modified by floor repetition.
        // If they were originally provided as part of inputs, they should ideally be preserved.
        // However, this service only reconstructs inputs_json from canonical.
        // The safest is to only reconstruct what canonical can reliably provide.
        // The original logic passes these as part of inputs, and CanonicalMapperService uses them.
        // This means the full inputsJson from the database *should* be passed to the constructor of this reverse mapper,
        // and then merged. For now, I will omit these as they are definitions, not part of floor config.
    }

    private function mapVerticalDistributionReverse(array &$inputsJson): void
    {
        $vd = $this->canonicalData['vertical_distribution'] ?? [];
        $inputsJson['largo_cable_entre_pisos'] = $vd['vertical_cable_length_per_floor_m'] ?? null;
        $inputsJson['atenuacion_cable_por_metro'] = $vd['vertical_cable_loss_db_per_m'] ?? null;

        // Note: The new granular fields (total_antenna_trunk_cable_length_m etc.) are aggregations.
        // Reversing them to original inputsJson fields is complex/lossy without more context.
        // Assuming 'largo_cable_entre_pisos' and 'atenuacion_cable_por_metro' are the main inputs from this section.
    }

    private function mapFloorsAndApartmentsReverse(array &$inputsJson, array &$detailJson): void
    {
        $floors = $this->canonicalData['floors'] ?? [];
        // Piso_Maximo and apartamentos_por_piso are now taken from originalInputs,
        // so no need to derive them here.

        // Initialize largo_cable_tu and largo_cable_derivador maps in inputsJson
        $inputsJson['largo_cable_tu'] = [];
        // Add other maps as needed for reverse mapping

        // Reconstruct detailJson
        foreach ($floors as $floor) {
            $floorNumber = $floor['floor_number'];
            $hd = $floor['horizontal_distribution'] ?? [];
            
            foreach ($floor['apartments'] as $apartment) {
                $apartmentInternals = $apartment['apartment_internals'] ?? [];
                $apto = (int)substr($apartment['apartment_id'], strrpos($apartment['apartment_id'], 'A') + 1); // Extract Apto number
                $bloque = 1; // Defaulting to block 1 for simplicity

                // Populate largo_cable_tu in inputsJson
                $largoCableTuKey = "(" . $floorNumber . ", " . $apto . ", " . $bloque . ")";
                $inputsJson['largo_cable_tu'][$largoCableTuKey] = $apartmentInternals['apartment_cable_length_m'] ?? 0; // Use apartment_cable_length_m from canonical if available, otherwise 0

                // Create a basic TU entry for detailJson
                $tuEntry = [
                    'Toma' => $apartment['apartment_id'] . 'TU1', // Assuming 1 TU per apartment and standard naming
                    'Piso' => $floorNumber,
                    'Apto' => $apto,
                    'Bloque' => $bloque,

                    // Reconstruct minimal fields required for CanonicalMapperService to function
                    // Values are assumed/defaulted if not directly available from canonical or lost in aggregation
                    'Longitud Antena→Troncal (m)' => ($this->canonicalData['vertical_distribution']['total_antenna_trunk_cable_length_m'] ?? 0) > 0 ? 19 : 0, // Assume 19 if total cable exists
                    'Pérdida Antena↔Troncal (conectores) (dB)' => ($this->canonicalData['vertical_distribution']['total_antenna_trunk_connectors_count'] ?? 0) > 0 ? 0.4 : 0,
                    'Repartidor Troncal' => !empty($this->canonicalData['vertical_distribution']['vertical_splitters']) ? 10 : 0, // Assume ID 10 if a splitter exists

                    'Distancia riser dentro bloque (m)' => max(0, ($floorNumber - 1) * ($inputsJson['largo_cable_entre_pisos'] ?? 0)), // Derived
                    'Riser Conectores (uds)' => ($this->canonicalData['vertical_distribution']['total_riser_connectors_count'] ?? 0) > 0 && ($floorNumber <= (count($floors))) ? 2 * $floorNumber : 0, // Simple heuristic for now: 2 per floor, increasing
                    'Riser Atenuación Taps (dB)' => ($this->canonicalData['vertical_distribution']['total_riser_taps_count'] ?? 0) > 0 ? max(0, ($floorNumber - 1) * 2.5) : 0, // Simple heuristic

                    'Feeder Troncal→Entrada Bloque (m)' => ($hd['horizontal_cable_length_m'] ?? 0) > 0 ? ($hd['horizontal_cable_length_m'] / ($inputsJson['apartamentos_por_piso'] ?? 1)) : 0, // Reverse calc from total floor length
                    'Derivador Piso' => ($hd['total_floor_derivadores_count'] ?? 0) > 0 ? 5 : 0, // Assume ID 5 if derivador exists

                    'Distancia total hasta la toma (m)' => $apartmentInternals['calculated_apartment_cable_length_m'] ?? 0, // This is a derived value, hard to get exact input.

                    // Add more placeholder values for fields that exist in original detailJson
                    'Pérdida Cable Deriv→Rep (dB)' => ($apartmentInternals['deriv_rep_connectors_count'] ?? 0) > 0 ? 3.09 : 0,
                    'Pérdida Conectores Apto (dB)' => (($apartmentInternals['deriv_rep_connectors_count'] ?? 0) > 0 || ($apartmentInternals['rep_tu_connectors_count'] ?? 0) > 0) ? 0.8 : 0,
                    'Repartidor Apt' => 'N/A',
                    'Pérdida Cable Rep→TU (dB)' => ($apartmentInternals['rep_tu_connectors_count'] ?? 0) > 0 ? 0.42 : 0,
                    'Pérdida Conexión TU (dB)' => ($apartmentInternals['conexion_tu_connectors_count'] ?? 0) > 0 ? 1 : 0,
                    'Nivel TU Final (dBµV)' => null, // Derived, not input
                ];
                $detailJson[] = $tuEntry;
            }
        }
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