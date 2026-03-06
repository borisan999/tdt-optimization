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

        $floors_map = [];
        $riser_block_lengths_per_floor = [];
        $riser_connectors_per_floor = [];
        $riser_taps_per_floor = [];
        
        foreach ($detail as $tu_row) {
            $piso = (int)($tu_row['piso'] ?? $tu_row['Piso'] ?? 0);
            $apto = (int)($tu_row['apto'] ?? $tu_row['Apto'] ?? 0);
            $tu_id = $tu_row['tu_id'] ?? $tu_row['Toma'] ?? 'Unknown';

            if (!isset($floors_map[$piso])) {
                $floors_map[$piso] = [
                    'floor_number' => $piso,
                    'apartments' => [],
                    'horizontal_distribution' => [
                        'horizontal_cable_length_m' => 0.0,
                        'horizontal_connectors_count' => 0,
                        'total_floor_derivadores_count' => 0,
                        'derivador_model' => null,
                    ],
                ];
                // Vertical components per floor (max across TUs in that floor)
                $riser_block_lengths_per_floor[$piso] = 0.0;
                $riser_connectors_per_floor[$piso] = 0;
                $riser_taps_per_floor[$piso] = 0;
            }

            // Track unique horizontal distribution per floor (assuming all TUs in a floor share the same feeder/derivador)
            $floors_map[$piso]['horizontal_distribution']['horizontal_cable_length_m'] = max(
                $floors_map[$piso]['horizontal_distribution']['horizontal_cable_length_m'],
                (float)($tu_row['Feeder Troncal→Entrada Bloque (m)'] ?? 0.0)
            );
            
            // For connectors and derivadores, we take the max per floor as well (assuming sharing)
            // or we could use a different logic, but max(1) is better than sum(TUs)
            if (!empty($tu_row['Derivador Piso']) && $tu_row['Derivador Piso'] !== 'N/A') {
                $floors_map[$piso]['horizontal_distribution']['total_floor_derivadores_count'] = max(
                    $floors_map[$piso]['horizontal_distribution']['total_floor_derivadores_count'],
                    1
                );
                $floors_map[$piso]['horizontal_distribution']['derivador_model'] = $tu_row['Derivador Piso'];
            }

            
            // Feeder connectors usually 2 per floor entry
            if ((float)($tu_row['Feeder Troncal→Entrada Bloque (m)'] ?? 0.0) > 0) {
                $floors_map[$piso]['horizontal_distribution']['horizontal_connectors_count'] = max(
                    $floors_map[$piso]['horizontal_distribution']['horizontal_connectors_count'],
                    2
                );
            }

            // Vertical tracking
            $riser_block_lengths_per_floor[$piso] = max($riser_block_lengths_per_floor[$piso], (float)($tu_row['Distancia riser dentro bloque (m)'] ?? 0.0));
            $riser_connectors_per_floor[$piso] = max($riser_connectors_per_floor[$piso], (int)($tu_row['Riser Conectores (uds)'] ?? 0));
            
            if ((float)($tu_row['Riser Atenuación Taps (dB)'] ?? 0.0) > 0) {
                $riser_taps_per_floor[$piso] = max($riser_taps_per_floor[$piso], 1);
            }

            if (!isset($floors_map[$piso]['apartments'][$apto])) {
                $floors_map[$piso]['apartments'][$apto] = [
                    'apartment_id' => "F{$piso}_A{$apto}",
                    'tu_count' => 0,
                    'apartment_internals' => [
                        'calculated_apartment_cable_length_m' => 0.0,
                        'deriv_rep_connectors_count' => 0,
                        'rep_tu_connectors_count' => 0,
                        'conexion_tu_connectors_count' => 0,
                        'repartidor_model' => null,
                        'tomas' => [],
                    ],
                ];
            }

            $apt = &$floors_map[$piso]['apartments'][$apto];
            $apt['tu_count']++;
            $apt['apartment_internals']['tomas'][] = $tu_id;

            if (!empty($tu_row['Repartidor Apt']) && $tu_row['Repartidor Apt'] !== 'N/A') {
                $apt['apartment_internals']['repartidor_model'] = $tu_row['Repartidor Apt'];
            }


            // Apartment cable: segment1 (deriv-rep) + segment2 (rep-tu)
            // We sum these because they are per TU? No, deriv-rep is usually per apartment (one cable to repartidor)
            // but rep-tu is per TU.
            $distDerivRep = (float)($tu_row['Pérdida Cable Deriv→Rep (dB)'] ?? 0.0) > 0 ? (float)($inputs['largo_cable_derivador_repartidor']["{$piso}|{$apto}"] ?? 0.0) : 0.0;
            
            // We use max for deriv-rep because it's shared for all TUs in the apartment
            $apt['apartment_internals']['calculated_apartment_cable_length_m'] = max($apt['apartment_internals']['calculated_apartment_cable_length_m'], $distDerivRep);
            
            // Add the rep-tu segment for THIS TU
            $tuKey = "{$piso}|{$apto}|" . count($apt['apartment_internals']['tomas']);
            $distRepTu = (float)($inputs['largo_cable_tu'][$tuKey] ?? $inputs['largo_cable_tu']["{$piso}|{$apto}|1"] ?? 0.0);
            $apt['apartment_internals']['calculated_apartment_cable_length_m'] += $distRepTu;

            // Connectors
            if ($distDerivRep > 0) $apt['apartment_internals']['deriv_rep_connectors_count'] = 2; // Per apartment
            if ($distRepTu > 0) $apt['apartment_internals']['rep_tu_connectors_count'] += 2; // Per TU
            if ((float)($tu_row['Pérdida Conexión TU (dB)'] ?? 0.0) > 0) $apt['apartment_internals']['conexion_tu_connectors_count'] += 1; // Per TU
        }

        ksort($floors_map);
        $final_floors = [];
        foreach ($floors_map as $pData) {
            ksort($pData['apartments']);
            $pData['apartments'] = array_values($pData['apartments']);
            $final_floors[] = $pData;
        }

        $floorCount = (int)($inputs['Piso_Maximo'] ?? 0);
        
        $vertical_splitters = [];
        $unique_troncal_repartidores = [];
        foreach ($detail as $tu_row) {
            if (!empty($tu_row['Repartidor Troncal']) && $tu_row['Repartidor Troncal'] !== 'N/A') {
                $model = $tu_row['Repartidor Troncal'];
                if (!isset($unique_troncal_repartidores[$model])) {
                    $unique_troncal_repartidores[$model] = true;
                    $vertical_splitters[] = [
                        'splitter_model' => "Repartidor Troncal ($model)",
                        'quantity' => 1
                    ];
                }
            }
        }

        $vertical_distribution = [
            'floor_count' => $floorCount,
            'total_antenna_trunk_cable_length_m' => (float)($inputs['largo_cable_amplificador_ultimo_piso'] ?? 0.0),
            'total_riser_block_cable_length_m' => array_sum($riser_block_lengths_per_floor),
            'total_riser_connectors_count' => array_sum($riser_connectors_per_floor),
            'total_antenna_trunk_connectors_count' => ((float)($inputs['largo_cable_amplificador_ultimo_piso'] ?? 0.0) > 0) ? 2 : 0,
            'total_riser_taps_count' => array_sum($riser_taps_per_floor),
            'vertical_splitters' => $vertical_splitters,
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
