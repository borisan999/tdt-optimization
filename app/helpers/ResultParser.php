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
            'solver_status' => $this->row['solver_status'] ?? null, // Add solver_status
            'solver_log'    => $this->row['solver_log'] ?? null,    // Add solver_log
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

            // Compatibility Shim: Map legacy keys to new standardized keys
            if (isset($tu['Toma']) && !isset($tu['tu_id'])) $tu['tu_id'] = $tu['Toma'];
            if (isset($tu['Piso']) && !isset($tu['piso'])) $tu['piso'] = $tu['Piso'];
            if (isset($tu['Apto']) && !isset($tu['apto'])) $tu['apto'] = $tu['Apto'];
            if (isset($tu['Bloque']) && !isset($tu['bloque'])) $tu['bloque'] = $tu['Bloque'];
            if (isset($tu['Nivel TU Final (dBµV)']) && !isset($tu['nivel_tu'])) $tu['nivel_tu'] = $tu['Nivel TU Final (dBµV)'];
            
            // Fallback for missing fields in very old results
            if (!isset($tu['nivel_min'])) $tu['nivel_min'] = $this->inputs['Nivel_minimo'] ?? 48;
            if (!isset($tu['nivel_max'])) $tu['nivel_max'] = $this->inputs['Nivel_maximo'] ?? 69;
            if (!isset($tu['cumple'])) {
                $val = (float)($tu['nivel_tu'] ?? 0);
                $tu['cumple'] = ($val >= (float)$tu['nivel_min'] && $val <= (float)$tu['nivel_max']) ? 1 : 0;
            }
            if (!isset($tu['losses'])) $tu['losses'] = [];

            $requiredKeys = [
                'tu_id','piso','apto','bloque',
                'nivel_tu','nivel_min','nivel_max',
                'cumple','losses'
            ];

            foreach ($requiredKeys as $key) {
                if (!array_key_exists($key, $tu)) {
                    throw new \DomainException("Missing '{$key}' in TU at index {$index}. Keys present: " . implode(', ', array_keys($tu)));
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

        $topology = $this->buildTopology();

        $canonical = [
            'schema_version' => 1, // Added for future-proofing
            'detail'  => $normalizedDetail,
            'summary' => $this->summary,
            'inputs'  => $this->inputs,
            'vertical_distribution' => $topology['vertical_distribution'],
            'floors' => $topology['floors'],
            'warnings' => $this->warnings(), // Add warnings to canonical output
        ];

        $this->validateCanonicalStructure($canonical);

        return $canonical;
    }

    private function validateCanonicalStructure(array $canonical): void
    {
        $requiredKeys = [
            'vertical_distribution',
            'floors',
            'detail',
            'warnings'
        ];

        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $canonical)) {
                throw new \RuntimeException("Canonical structure missing key: {$key}");
            }
        }

        if (!is_array($canonical['floors'])) {
            throw new \RuntimeException("Canonical floors must be array");
        }

        if (!is_array($canonical['vertical_distribution'])) {
            throw new \RuntimeException("Canonical vertical_distribution must be array");
        }
        
        if (!is_array($canonical['detail'])) {
            throw new \RuntimeException("Canonical detail must be array");
        }

        $requiredTuKeys = ['tu_id', 'piso', 'apto', 'nivel_tu', 'cumple'];
        foreach ($canonical['detail'] as $index => $tu) {
            if (!is_array($tu)) {
                throw new \RuntimeException("TU entry at index {$index} must be array");
            }
            foreach ($requiredTuKeys as $key) {
                if (!array_key_exists($key, $tu)) {
                    throw new \RuntimeException("TU entry at index {$index} is missing key: {$key}");
                }
            }
        }
    }

    private function buildTopology(): array
    {
        $detail = $this->detail;
        $inputs = $this->inputs;

        $floors_map = []; // Temporary map to build floors and apartments
        $vertical_data_accumulator = [ // Accumulate vertical components as floats
            'total_antenna_trunk_cable_length_m' => 0.0,
            'total_riser_block_cable_length_m' => 0.0,
            'total_riser_connectors_count' => 0.0,
            'total_antenna_trunk_connectors_count' => 0.0,
            'vertical_splitters' => [], // Store splitter models/counts
            'total_riser_taps_count' => 0.0,
        ];
        
        // Use a map to prevent double counting of vertical components from different TUs in the same floor
        $riser_block_lengths_per_floor = [];
        $riser_connector_attenuation_per_floor = []; // Store attenuation as float
        $riser_taps_attenuation_per_floor = []; // Store attenuation as float


        foreach ($detail as $tu_row) {
            if (!isset($tu_row['piso'], $tu_row['apto'], $tu_row['losses'])) {
                continue; // skip malformed rows safely
            }

            $floor_num = (int)$tu_row['piso'];
            $apt_num   = (int)$tu_row['apto'];
            $tu_id = $tu_row['tu_id'];

            // Initialize floor and apartment structures if not present
            if (!isset($floors_map[$floor_num])) {
                $floors_map[$floor_num] = [
                    'floor_number' => $floor_num,
                    'apartments' => [],
                    'horizontal_distribution' => [
                        'horizontal_cable_length_m' => 0.0,
                        'horizontal_connectors_count' => 0.0, // Accumulate as float
                        'total_floor_derivadores_count' => 0,
                    ],
                ];
            }
            if (!isset($floors_map[$floor_num]['apartments'][$apt_num])) {
                $floors_map[$floor_num]['apartments'][$apt_num] = [
                    'apartment_id' => "F{$floor_num}_A{$apt_num}",
                    'tu_count' => 0,
                    'apartment_internals' => [
                        'calculated_apartment_cable_length_m' => 0.0,
                        'deriv_rep_connectors_count' => 0.0, // Accumulate as float
                        'rep_tu_connectors_count' => 0.0, // Accumulate as float
                        'conexion_tu_connectors_count' => 0.0, // Accumulate as float
                        'tomas' => [], // Store TU IDs here
                    ],
                ];
            }

            // Aggregate TU count and store TU ID
            $floors_map[$floor_num]['apartments'][$apt_num]['tu_count']++;
            $floors_map[$floor_num]['apartments'][$apt_num]['apartment_internals']['tomas'][] = $tu_id;

                    // Process losses for horizontal and apartment internals, and accumulate vertical data per floor
                    foreach ($tu_row['losses'] as $loss) {
                        switch ($loss['segment']) {
                            case 'riser_dentro_del_bloque':
                                $riser_block_lengths_per_floor[$floor_num] = max($riser_block_lengths_per_floor[$floor_num] ?? 0.0, (float)($loss['value'] ?? 0.0));
                                break;
                            case 'riser_atenuacion_conectores':
                                $riser_connector_attenuation_per_floor[$floor_num] = max($riser_connector_attenuation_per_floor[$floor_num] ?? 0.0, (float)($loss['value'] ?? 0.0));
                                break;
                            case 'riser_atenuacin_taps':
                                $riser_taps_attenuation_per_floor[$floor_num] = max($riser_taps_attenuation_per_floor[$floor_num] ?? 0.0, (float)($loss['value'] ?? 0.0));
                                break;
                            case 'feeder_cable': // Horizontal cable length per floor
                                $floors_map[$floor_num]['horizontal_distribution']['horizontal_cable_length_m'] += (float)($loss['value'] ?? 0.0);
                                break;
                            case 'feeder_conectores': // Horizontal connectors per floor, accumulate as float
                                $floors_map[$floor_num]['horizontal_distribution']['horizontal_connectors_count'] += (float)($loss['value'] ?? 0.0);
                                break;
                            case 'derivador_piso': // Derivadores per floor, count as integer
                                $floors_map[$floor_num]['horizontal_distribution']['total_floor_derivadores_count'] += 1;
                                break;
                            case 'cable_derivrep':
                                $floors_map[$floor_num]['apartments'][$apt_num]['apartment_internals']['deriv_rep_connectors_count'] += 2.0; // Accumulate as float
                                break;
                            case 'cable_reptu':
                                $floors_map[$floor_num]['apartments'][$apt_num]['apartment_internals']['rep_tu_connectors_count'] += 2.0; // Accumulate as float
                                break;
                            case 'conexin_tu':
                                $floors_map[$floor_num]['apartments'][$apt_num]['apartment_internals']['conexion_tu_connectors_count'] += 1.0; // Accumulate as float
                                break;
                            case 'total':
                                // Just a marker
                                break;
                        }
                    }
                }
                
                // Finalize vertical distribution accumulator for rounding
                $vertical_data_accumulator['total_antenna_trunk_cable_length_m'] = (float)($inputs['largo_cable_amplificador_ultimo_piso'] ?? 0.0);
                $vertical_data_accumulator['total_antenna_trunk_conectores_count'] = ($vertical_data_accumulator['total_antenna_trunk_cable_length_m'] > 0.0) ? 2.0 : 0.0;
            
                foreach ($riser_block_lengths_per_floor as $length) {
                    $vertical_data_accumulator['total_riser_block_cable_length_m'] += $length;
                }
                // Sum attenuations as floats, then round the total sum
                $total_riser_connector_attenuation = array_sum($riser_connector_attenuation_per_floor);
                $vertical_data_accumulator['total_riser_connectors_count'] += $total_riser_connector_attenuation / 0.2; // Keep as float for now
            
                $total_riser_taps_attenuation = array_sum($riser_taps_attenuation_per_floor);
                $vertical_data_accumulator['total_riser_taps_count'] += $total_riser_taps_attenuation / 5.0; // Keep as float for now
                
                // Vertical splitters from inputs
                $vertical_splitters_counts = [];
                if (isset($inputs['derivadores_data']) && is_array($inputs['derivadores_data'])) {
                    foreach ($inputs['derivadores_data'] as $model => $specs) {
                        // This logic might be complex if we don't know which were used. 
                        // For now, if detail shows a derivador model, we could count it.
                        // But buildTopology is for inventory.
                    }
                }
                
                // Finalize floors data (horizontal_distribution and apartment_internals) with rounding
                foreach ($floors_map as $floor_num => &$floorData) {
                    // Apply rounding for horizontal_connectors_count after all aggregations for this floor
                    $floorData['horizontal_distribution']['horizontal_connectors_count'] = (int)round($floorData['horizontal_distribution']['horizontal_connectors_count']);
                    
                    foreach ($floorData['apartments'] as $apt_num => &$aptData) {
                        $key = "{$floor_num}|{$apt_num}";
                        $aptData['apartment_internals']['calculated_apartment_cable_length_m'] = (float)($inputs['largo_cable_derivador_repartidor'][$key] ?? 0.0);
                        
                        // Round connector counts for apartment internals
                        if ($aptData['apartment_internals']['calculated_apartment_cable_length_m'] > 0.0) {
                            $aptData['apartment_internals']['deriv_rep_connectors_count'] = (int)round($aptData['apartment_internals']['deriv_rep_connectors_count']);
                            $aptData['apartment_internals']['rep_tu_connectors_count'] = (int)round($aptData['apartment_internals']['rep_tu_connectors_count']);
                        }
                        $aptData['apartment_internals']['conexion_tu_connectors_count'] = (int)round((float)($inputs['atenuacion_conexion_tu'] ?? 0.0) > 0.0 ? 1.0 : 0.0);
                    }
                }        unset($aptData, $floorData);


        ksort($floors_map);

        // Convert to indexed arrays as expected by the aggregator
        $final_floors = [];
        foreach ($floors_map as &$floorData) {
            ksort($floorData['apartments']);
            $floorData['apartments'] = array_values($floorData['apartments']); // Convert apts to indexed array
            $final_floors[] = $floorData;
        }
        unset($floorData);

        // Calculate floor_count more robustly
        $maxFloorFromDetail = empty($floors_map) ? 0 : max(array_keys($floors_map));
        $floorCountFromInputs = (int)($inputs['Piso_Maximo'] ?? 0);
        $floorCount = max($maxFloorFromDetail, $floorCountFromInputs);

        // Final rounding for vertical distribution totals
        $vertical_distribution = [
            'floor_count' => $floorCount,
            'cable_between_floors' => (float)($inputs['largo_cable_entre_pisos'] ?? 0.0),
            'feeder_length' => (float)($inputs['largo_cable_feeder_bloque'] ?? 0.0),
            // Aggregated values
            'total_antenna_trunk_cable_length_m' => (float)$vertical_data_accumulator['total_antenna_trunk_cable_length_m'],
            'total_riser_block_cable_length_m' => (float)$vertical_data_accumulator['total_riser_block_cable_length_m'],
            'total_riser_connectors_count' => (int)round($vertical_data_accumulator['total_riser_connectors_count']), // Round here
            'total_antenna_trunk_connectors_count' => (int)round($vertical_data_accumulator['total_antenna_trunk_conectores_count']), // Round here
            'vertical_splitters' => $vertical_data_accumulator['vertical_splitters'],
            'total_riser_taps_count' => (int)round($vertical_data_accumulator['total_riser_taps_count']), // Round here
        ];

        return [
            'vertical_distribution' => $vertical_distribution,
            'floors' => $final_floors
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
