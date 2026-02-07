<?php

namespace app\helpers;

class InventoryAggregator
{
    private array $detail;
    private array $aggregatedInventory;
    private array $allTotals; // To store global totals and subtotals

    public function __construct(array $detail)
    {
        $this->detail = $detail;
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
        return [
            'inventory' => $this->aggregatedInventory,
            'totals'    => $this->allTotals,
        ];
    }

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