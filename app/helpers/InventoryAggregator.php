<?php

namespace app\helpers;

use Throwable;

class InventoryAggregator
{
    private array $canonicalData;
    private array $aggregatedInventory;
    private array $allTotals; // To store global totals and subtotals

    public function __construct(array $canonicalData)
    {
        $this->canonicalData = $canonicalData;
        $this->aggregatedInventory = [
            'Vertical Distribution' => [],
            'Horizontal Distribution' => [],
            'Apartment Interior' => [],
        ];
        $this->allTotals = [
            'Vertical Distribution' => [],
            'Horizontal Distribution' => [],
            'Horizontal Floor Subtotals' => [], // For per-floor subtotals
            'Apartment Interior' => [],
            'Grand Total' => [],
        ];
    }

    public function aggregate(): array
    {
        $this->processDetailForAggregation();
        $this->calculateGlobalTotals();
        $this->calculateScopeSummaries();
        $this->groupIdenticalApartments(); // New step for Task 2.2
        return [
            'inventory' => $this->aggregatedInventory,
            'totals'    => $this->allTotals,
        ];
    }

    /**
     * Groups apartments with identical components across floors.
     * Result stored in $this->aggregatedInventory['Grouped Apartment Interior']
     */
    private function groupIdenticalApartments(): void
    {
        $grouped = [];
        $aptInventory = $this->aggregatedInventory['Apartment Interior'];

        // 1. Collect all identical apartments regardless of floor
        // key: JSON representation of components
        // val: list of [floor, apto]
        $fingerprints = [];

        foreach ($aptInventory as $piso => $apts) {
            foreach ($apts as $apto => $components) {
                // Sort components by name to ensure consistent fingerprint
                usort($components, fn($a, $b) => strcmp($a['Componente'], $b['Componente']));
                $fingerprint = json_encode($components);
                
                if (!isset($fingerprints[$fingerprint])) {
                    $fingerprints[$fingerprint] = [
                        'components' => $components,
                        'locations' => []
                    ];
                }
                $fingerprints[$fingerprint]['locations'][] = ['piso' => $piso, 'apto' => $apto];
            }
        }

        // 2. Format locations into readable strings (e.g., "Pisos 1-5, Aptos 1,2")
        foreach ($fingerprints as $data) {
            $locations = $data['locations'];
            
            // Group by floor range
            $byFloor = [];
            foreach ($locations as $loc) {
                $byFloor[$loc['piso']][] = $loc['apto'];
            }
            ksort($byFloor);

            $locationStrings = [];
            // Simplified grouping: if all floors have same apartments, group floors
            // For now, let's just list them clearly
            foreach ($byFloor as $piso => $apts) {
                sort($apts);
                $aptStr = implode(', ', $apts);
                $locationStrings[] = "Piso $piso (Aptos $aptStr)";
            }

            $grouped[] = [
                'Location' => implode('; ', $locationStrings),
                'Components' => $data['components']
            ];
        }

        $this->aggregatedInventory['Grouped Apartment Interior'] = $grouped;
    }

    private function calculateScopeSummaries(): void
    {
        $this->allTotals['Scope Summaries'] = [
            'Vertical' => ['cable_m' => 0, 'equipment_uds' => 0, 'connectors_uds' => 0],
            'Horizontal' => ['cable_m' => 0, 'equipment_uds' => 0, 'connectors_uds' => 0],
            'Apartamento' => ['cable_m' => 0, 'equipment_uds' => 0, 'connectors_uds' => 0],
        ];

        foreach ($this->allTotals['Vertical Distribution'] as $item) {
            $this->accumulateToScope('Vertical', $item);
        }
        foreach ($this->allTotals['Horizontal Distribution'] as $item) {
            $this->accumulateToScope('Horizontal', $item);
        }
        foreach ($this->allTotals['Apartment Interior'] as $item) {
            $this->accumulateToScope('Apartamento', $item);
        }
    }

    private function accumulateToScope(string $scope, array $item): void
    {
        if ($item['Tipo'] === 'Cable') {
            $this->allTotals['Scope Summaries'][$scope]['cable_m'] += $item['Cantidad'];
        } elseif ($item['Tipo'] === 'Equipo') {
            $this->allTotals['Scope Summaries'][$scope]['equipment_uds'] += $item['Cantidad'];
        } elseif ($item['Tipo'] === 'Conector') {
            $this->allTotals['Scope Summaries'][$scope]['connectors_uds'] += $item['Cantidad'];
        }
    }


    /*
    private function processDetailForAggregation(): void
    {
        $verticalComponents = [];
        $horizontalComponents = []; // Keyed by floor
        $apartmentComponents = []; // Keyed by floor, then apartment

        foreach ($this->detail as $tu) {
            $piso = $tu['Piso'] ?? 'N/A';
            $apto = $tu['Apto'] ?? 'N/A';

            // --- Vertical Distribution ---
            $scope = 'Vertical';
            $lengthAntenaTroncal = (float)($tu['Longitud Antena→Troncal (m)'] ?? 0);
            $lengthRiserBloque = (float)($tu['Distancia riser dentro bloque (m)'] ?? 0);

            $this->addComponent($verticalComponents, $scope, 'Cable Troncal Vertical', 'm', $lengthAntenaTroncal, 'Por bloque');
            $this->addComponent($verticalComponents, $scope, 'Cable Riser Vertical', 'm', $lengthRiserBloque, 'Por piso');

            $riserConnectors = (int)($tu['Riser Conectores (uds)'] ?? 0);
            $this->addComponent($verticalComponents, $scope, 'Conector F (Riser)', 'uds.', $riserConnectors, 'En conexiones de Riser');
            if ($lengthAntenaTroncal > 0) {
                 $this->addComponent($verticalComponents, $scope, 'Conector F (Antena-Troncal)', 'uds.', 2, '2 por tramo de cable');
            }

            if (!empty($tu['Repartidor Troncal'])) {
                $this->addComponent($verticalComponents, $scope, 'Repartidor Troncal', 'uds.', 1, 'Ubicado en el troncal');
            }
            if (!empty($tu['Riser Atenuación Taps (dB)']) && (float)$tu['Riser Atenuación Taps (dB)'] > 0) {
                 $this->addComponent($verticalComponents, $scope, 'Tap de Riser', 'uds.', 1, 'Taps en el Riser');
            }


            // --- Horizontal Distribution (per Floor) ---
            if (!isset($horizontalComponents[$piso])) {
                $horizontalComponents[$piso] = [];
            }
            $scope = 'Horizontal';
            $lengthFeeder = (float)($tu['Feeder Troncal→Entrada Bloque (m)'] ?? 0);
            $this->addComponent($horizontalComponents[$piso], $scope, 'Cable Horizontal por Piso', 'm', $lengthFeeder, 'Por piso');

            if ($lengthFeeder > 0) {
                $this->addComponent($horizontalComponents[$piso], $scope, 'Conector F (Feeder)', 'uds.', 2, '2 por tramo de cable');
            }

            if (!empty($tu['Derivador Piso'])) {
                 $this->addComponent($horizontalComponents[$piso], $scope, 'Derivador de Piso', 'uds.', 1, 'Ubicado en el piso');
            }


            // --- Apartment Interior (per Floor, per Apartment) ---
            if (!isset($apartmentComponents[$piso])) {
                $apartmentComponents[$piso] = [];
            }
            if (!isset($apartmentComponents[$piso][$apto])) {
                $apartmentComponents[$piso][$apto] = [];
            }
            $scope = 'Apartamento';

            $totalDistanceToTu = (float)($tu['Distancia total hasta la toma (m)'] ?? 0);
            $nonApartmentCableLength = $lengthAntenaTroncal + $lengthRiserBloque + $lengthFeeder;
            $apartmentCableLength = max(0, $totalDistanceToTu - $nonApartmentCableLength);

            if ($apartmentCableLength > 0) {
                $this->addComponent($apartmentComponents[$piso][$apto], $scope, 'Cable Interior Apartamento', 'm', $apartmentCableLength, 'Desde el repartidor hasta la toma');
            }

            if ((float)($tu['Pérdida Cable Deriv→Rep (dB)'] ?? 0) > 0) {
                 $this->addComponent($apartmentComponents[$piso][$apto], $scope, 'Conector F (Apto Deriv-Rep)', 'uds.', 2, '2 por tramo de cable');
            }
            if ((float)($tu['Pérdida Cable Rep→TU (dB)'] ?? 0) > 0) {
                 $this->addComponent($apartmentComponents[$piso][$apto], $scope, 'Conector F (Apto Rep-TU)', 'uds.', 2, '2 por tramo de cable');
            }
            if ((float)($tu['Pérdida Conexión TU (dB)'] ?? 0) > 0) {
                $this->addComponent($apartmentComponents[$piso][$apto], $scope, 'Conector F (Conexión TU)', 'uds.', 1, 'En la conexión final a la Toma de Usuario');
            }

            if (!empty($tu['Repartidor Apt']) && $tu['Repartidor Apt'] !== 'N/A') {
                $this->addComponent($apartmentComponents[$piso][$apto], $scope, 'Repartidor Apartamento', 'uds.', 1, 'Ubicado en el apartamento');
            }
            $this->addComponent($apartmentComponents[$piso][$apto], $scope, 'Toma de Usuario (TU)', 'uds.', 1, 'Punto de conexión final');
        }

        // Finalize aggregation into the main structure and calculate per-floor totals for Horizontal
        $this->aggregatedInventory['Vertical Distribution'] = $this->flattenAndSumComponents($verticalComponents);

        foreach ($horizontalComponents as $floor => $components) {
            $flattenedFloor = $this->flattenAndSumComponents($components);
            $this->aggregatedInventory['Horizontal Distribution'][$floor] = $flattenedFloor;
            $this->allTotals['Horizontal Floor Subtotals'][$floor] = $this->calculateSectionTotals($flattenedFloor, 'Horizontal');
        }

        foreach ($apartmentComponents as $floor => $apts) {
            foreach ($apts as $apto => $components) {
                $this->aggregatedInventory['Apartment Interior'][$floor][$apto] = $this->flattenAndSumComponents($components);
            }
        }
    }
    */

    private function processDetailForAggregation(): void
    {
        $verticalComponents = [];
        $horizontalComponents = []; // Keyed by floor
        $apartmentComponents = []; // Keyed by floor, then apartment

        // --- Vertical Distribution ---
        $scope = 'Vertical';
        $vd = $this->canonicalData['vertical_distribution'] ?? [];

        if (($vd['total_antenna_trunk_cable_length_m'] ?? 0) > 0) {
            $this->addComponent($verticalComponents, $scope, 'Cable Troncal Vertical', 'm', $vd['total_antenna_trunk_cable_length_m'], 'Por bloque');
        }
        if (($vd['total_riser_block_cable_length_m'] ?? 0) > 0) {
            $this->addComponent($verticalComponents, $scope, 'Cable Riser Vertical', 'm', $vd['total_riser_block_cable_length_m'], 'Por piso');
        }
        if (($vd['total_riser_connectors_count'] ?? 0) > 0) {
            $this->addComponent($verticalComponents, $scope, 'Conector F (Riser)', 'uds.', $vd['total_riser_connectors_count'], 'En conexiones de Riser');
        }
        if (($vd['total_antenna_trunk_connectors_count'] ?? 0) > 0) {
            $this->addComponent($verticalComponents, $scope, 'Conector F (Antena-Troncal)', 'uds.', $vd['total_antenna_trunk_connectors_count'], '2 por tramo de cable');
        }
        if (!empty($vd['vertical_splitters'])) {
            foreach ($vd['vertical_splitters'] as $splitter) {
                $splitterModel = $splitter['splitter_model'] ?? 'Repartidor Troncal';
                // In ResultParser, we already format it as "Repartidor Troncal (MODEL)"
                $this->addComponent($verticalComponents, $scope, $splitterModel, 'uds.', 1, 'Ubicado en el troncal');
            }
        }
        if (($vd['total_riser_taps_count'] ?? 0) > 0) {
            $this->addComponent($verticalComponents, $scope, 'Tap de Riser', 'uds.', $vd['total_riser_taps_count'], 'Taps en el Riser');
        }


        // --- Horizontal Distribution and Apartment Interior (per Floor) ---
        foreach (($this->canonicalData['floors'] ?? []) as $floor) {
            $piso = $floor['floor_number'];
            if (!isset($horizontalComponents[$piso])) {
                $horizontalComponents[$piso] = [];
            }
            if (!isset($apartmentComponents[$piso])) {
                $apartmentComponents[$piso] = [];
            }

            // Horizontal Distribution for this floor
            $hd = $floor['horizontal_distribution'] ?? [];
            $scope = 'Horizontal';

            if (($hd['horizontal_cable_length_m'] ?? 0) > 0) {
                $this->addComponent($horizontalComponents[$piso], $scope, 'Cable Horizontal por Piso', 'm', $hd['horizontal_cable_length_m'], 'Por piso');
            }
            if (($hd['horizontal_connectors_count'] ?? 0) > 0) {
                $this->addComponent($horizontalComponents[$piso], $scope, 'Conector F (Feeder)', 'uds.', $hd['horizontal_connectors_count'], '2 por tramo de cable');
            }
            if (($hd['total_floor_derivadores_count'] ?? 0) > 0) {
                $modelSuffix = !empty($hd['derivador_model']) ? " ({$hd['derivador_model']})" : "";
                $this->addComponent($horizontalComponents[$piso], $scope, "Derivador de Piso{$modelSuffix}", 'uds.', $hd['total_floor_derivadores_count'], 'Ubicado en el piso');
            }


            // Apartment Interior for this floor
            foreach (($floor['apartments'] ?? []) as $apartment) {
                $aptoMatch = [];
                preg_match('/_A(\d+)$/', $apartment['apartment_id'], $aptoMatch);
                $apto = $aptoMatch[1] ?? 'N/A';

                if (!isset($apartmentComponents[$piso][$apto])) {
                    $apartmentComponents[$piso][$apto] = [];
                }

                $apartmentInternals = $apartment['apartment_internals'] ?? [];
                $scope = 'Apartamento';

                if (($apartmentInternals['calculated_apartment_cable_length_m'] ?? 0) > 0) {
                    $this->addComponent($apartmentComponents[$piso][$apto], $scope, 'Cable Interior Apartamento', 'm', $apartmentInternals['calculated_apartment_cable_length_m'], 'Desde el repartidor hasta la toma');
                }
                if (($apartmentInternals['deriv_rep_connectors_count'] ?? 0) > 0) {
                    $this->addComponent($apartmentComponents[$piso][$apto], $scope, 'Conector F (Apto Deriv-Rep)', 'uds.', $apartmentInternals['deriv_rep_connectors_count'], '2 por tramo de cable');
                }
                if (($apartmentInternals['rep_tu_connectors_count'] ?? 0) > 0) {
                    $this->addComponent($apartmentComponents[$piso][$apto], $scope, 'Conector F (Apto Rep-TU)', 'uds.', $apartmentInternals['rep_tu_connectors_count'], '2 por tramo de cable');
                }
                if (($apartmentInternals['conexion_tu_connectors_count'] ?? 0) > 0) {
                    $this->addComponent($apartmentComponents[$piso][$apto], $scope, 'Conector F (Conexión TU)', 'uds.', $apartmentInternals['conexion_tu_connectors_count'], 'En la conexión final a la Toma de Usuario');
                }
                
                if (!empty($apartmentInternals['repartidor_model'])) {
                    $this->addComponent($apartmentComponents[$piso][$apto], $scope, "Repartidor Apartamento ({$apartmentInternals['repartidor_model']})", 'uds.', 1, 'Ubicado en el apartamento');
                }

                $tomas = $apartmentInternals['tomas'] ?? [];
                if (!empty($tomas)) {
                    $this->addComponent($apartmentComponents[$piso][$apto], $scope, 'Toma de Usuario (TU)', 'uds.', count($tomas), 'Punto de conexión final');
                }
            }
        }

        // Finalize aggregation into the main structure and calculate per-floor totals for Horizontal
        $this->aggregatedInventory['Vertical Distribution'] = $this->flattenAndSumComponents($verticalComponents);

        foreach ($horizontalComponents as $floor => $components) {
            $flattenedFloor = $this->flattenAndSumComponents($components);
            $this->aggregatedInventory['Horizontal Distribution'][$floor] = $flattenedFloor;
            $this->allTotals['Horizontal Floor Subtotals'][$floor] = $this->calculateSectionTotals($flattenedFloor, 'Horizontal');
        }

        foreach ($apartmentComponents as $floor => $apts) {
            foreach ($apts as $apto => $components) {
                $this->aggregatedInventory['Apartment Interior'][$floor][$apto] = $this->flattenAndSumComponents($components);
            }
        }
    }

    private function calculateGlobalTotals(): void
    {
        // Vertical Distribution Global Total
        $this->allTotals['Vertical Distribution'] = $this->calculateSectionTotals($this->aggregatedInventory['Vertical Distribution'], 'Vertical');

        // Horizontal Distribution Global Total
        $horizontalGlobalTotalsAccumulator = [];
        foreach ($this->aggregatedInventory['Horizontal Distribution'] as $floorItems) {
            $floorTotals = $this->calculateSectionTotals($floorItems, 'Horizontal');
            foreach ($floorTotals as $totalItem) {
                // Unique key for global accumulation: Tipo + Componente + Unidad
                $globalComponentKey = $totalItem['Tipo'] . '::' . $totalItem['Componente'] . '::' . $totalItem['Unidad'];
                if (!isset($horizontalGlobalTotalsAccumulator[$globalComponentKey])) {
                    $horizontalGlobalTotalsAccumulator[$globalComponentKey] = [
                        'Scope' => 'Horizontal', // Global total's scope
                        'Tipo' => $totalItem['Tipo'],
                        'Componente' => $totalItem['Componente'],
                        'Unidad' => $totalItem['Unidad'],
                        'Cantidad' => 0,
                    ];
                }
                $horizontalGlobalTotalsAccumulator[$globalComponentKey]['Cantidad'] += $totalItem['Cantidad'];
            }
        }
        $this->allTotals['Horizontal Distribution'] = array_values($horizontalGlobalTotalsAccumulator);

        // Apartment Interior Global Total
        $apartmentGlobalTotalsAccumulator = [];
        foreach ($this->aggregatedInventory['Apartment Interior'] as $floor => $apts) {
            foreach ($apts as $apto => $apartmentItems) {
                $apartmentTotals = $this->calculateSectionTotals($apartmentItems, 'Apartamento');
                foreach ($apartmentTotals as $totalItem) {
                    $globalComponentKey = $totalItem['Tipo'] . '::' . $totalItem['Componente'] . '::' . $totalItem['Unidad'];
                    if (!isset($apartmentGlobalTotalsAccumulator[$globalComponentKey])) {
                        $apartmentGlobalTotalsAccumulator[$globalComponentKey] = [
                            'Scope' => 'Apartamento', // Global total's scope
                            'Tipo' => $totalItem['Tipo'],
                            'Componente' => $totalItem['Componente'],
                            'Unidad' => $totalItem['Unidad'],
                            'Cantidad' => 0,
                        ];
                    }
                    $apartmentGlobalTotalsAccumulator[$globalComponentKey]['Cantidad'] += $totalItem['Cantidad'];
                }
            }
        }
        $this->allTotals['Apartment Interior'] = array_values($apartmentGlobalTotalsAccumulator);

        // Grand Total (Project)
        $grandTotalsAccumulator = [];
        $allGlobalTotals = array_merge(
            $this->allTotals['Vertical Distribution'],
            $this->allTotals['Horizontal Distribution'],
            $this->allTotals['Apartment Interior']
        );

        foreach ($allGlobalTotals as $totalItem) {
            // Special handling for connectors: consolidate all 'Conector F (X)' into 'Conector F Total'
            $componentName = $totalItem['Componente'];
            $tipo = $totalItem['Tipo'];
            if (str_contains($componentName, 'Conector F')) {
                $componentName = 'Conector F Total';
                $tipo = 'Conector'; // Ensure Tipo is consistent
            }

            $grandComponentKey = $tipo . '::' . $componentName . '::' . $totalItem['Unidad'];
            if (!isset($grandTotalsAccumulator[$grandComponentKey])) {
                $grandTotalsAccumulator[$grandComponentKey] = [
                    'Scope' => 'Proyecto',
                    'Tipo' => $tipo,
                    'Componente' => $componentName,
                    'Unidad' => $totalItem['Unidad'],
                    'Cantidad' => 0,
                ];
            }
            $grandTotalsAccumulator[$grandComponentKey]['Cantidad'] += $totalItem['Cantidad'];
        }
        $this->allTotals['Grand Total'] = array_values($grandTotalsAccumulator);
    }

    private function calculateSectionTotals(array $items, string $scopeContext = ''): array
    {
        $totals = [];
        foreach ($items as $item) {
            $scope = $item['Scope'] ?? $scopeContext;
            $tipo = $item['Tipo'] ?? $this->getCategoryFromComponentName($item['Componente']);
            $componentKey = $scope . '::' . $tipo . '::' . $item['Componente'] . '::' . $item['Unidad'];

            if (!isset($totals[$componentKey])) {
                $totals[$componentKey] = [
                    'Scope' => $scope,
                    'Tipo' => $tipo,
                    'Componente' => $item['Componente'],
                    'Unidad' => $item['Unidad'],
                    'Cantidad' => 0,
                ];
            }
            $totals[$componentKey]['Cantidad'] += $item['Cantidad'];
        }
        return array_values($totals);
    }


    /**
     * Adds or sums a component's quantity to the target array.
     * The key for the target array is a combination of scope, componentName and observation to handle distinct items.
     * Includes a 'Scope' for filtering.
     */
    private function addComponent(array &$target, string $scope, string $componentName, string $unit, float|int $quantity, ?string $observation = null): void
    {
        // Capitalize the first letter of the observation
        if ($observation) {
            $observation = ucfirst($observation);
        }

        $key = $scope . '::' . $componentName . ($observation ? "::{$observation}" : ''); // Use "::" as a clearer separator for key
        if (!isset($target[$key])) {
            $target[$key] = [
                'Scope' => $scope,
                'Componente' => $componentName,
                'Unidad' => $unit,
                'Cantidad' => 0,
                'Observación' => $observation,
            ];
        }
        $target[$key]['Cantidad'] += $quantity;
    }

    /**
     * Flattens the component array and assigns a 'Tipo' based on component name.
     */
    private function flattenAndSumComponents(array $components): array
    {
        $flattened = [];
        foreach ($components as $item) {
            $outputItem = [
                'Scope' => $item['Scope'], // Add Scope column
                'Tipo' => $this->getCategoryFromComponentName($item['Componente']),
                'Componente' => $item['Componente'],
                'Unidad' => $item['Unidad'],
                'Cantidad' => round($item['Cantidad'], 2),
                'Observación' => $item['Observación'],
            ];
            $flattened[] = $outputItem;
        }
        return $flattened;
    }

    /**
     * Helper to determine 'Tipo' from 'Componente' for the export format.
     */
    private function getCategoryFromComponentName(string $componentName): string
    {
        if (str_contains($componentName, 'Cable')) return 'Cable';
        if (str_contains($componentName, 'Conector') || str_contains($componentName, 'Conexión')) return 'Conector';
        if (str_contains($componentName, 'Repartidor') || str_contains($componentName, 'Derivador') || str_contains($componentName, 'Tap') || str_contains($componentName, 'Toma')) return 'Equipo';
        return 'Otro';
    }
}
