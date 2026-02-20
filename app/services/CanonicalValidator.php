<?php

namespace App\Services;

require_once __DIR__ . '/CanonicalValidationException.php';

class CanonicalValidator
{
    private array $data;
    private array $errors = [];
    private array $apartmentSet = [];
    private int $computedMaxFloor = 0;

    private function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function validate(array $data): array
    {
        $validator = new self($data);
        $validator->run();

        if (!empty($validator->errors)) {
            return [
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Canonical validation failed',
                    'details' => $validator->errors
                ]
            ];
        }
        
        return [
            'success' => true,
            'data' => null,
            'error' => null
        ];
    }

    private function run(): void
    {
        $this->validateRequiredTopLevelKeys();
        
        // Stop early if critical keys are missing to avoid undefined index errors in later layers
        if (!empty($this->errors)) return;

        $this->validateScalarParameters();
        $this->validateEquipmentCatalog();
        $this->validateTopology();
        $this->validateCableMaps();
        $this->validateElectricalDomainRules();
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[] = [
            'field' => $field,
            'message' => $message
        ];
    }

    private function validateRequiredTopLevelKeys(): void
    {
        $required = [
            'Nivel_maximo',
            'Nivel_minimo',
            'Piso_Maximo',
            'Potencia_Objetivo_TU',
            'potencia_entrada',
            'p_troncal',
            'derivadores_data',
            'repartidores_data',
            'tus_requeridos_por_apartamento',
            'largo_cable_derivador_repartidor',
            'largo_cable_tu'
        ];

        foreach ($required as $key) {
            if (!isset($this->data[$key])) {
                $this->addError($key, 'Missing required top-level field');
            }
        }
    }

    private function validateScalarParameters(): void
    {
        $scalars = [
            'Nivel_maximo',
            'Nivel_minimo',
            'Piso_Maximo',
            'Potencia_Objetivo_TU',
            'potencia_entrada',
            'p_troncal'
        ];

        foreach ($scalars as $key) {
            $val = $this->data[$key];
            if (!is_numeric($val)) {
                $this->addError($key, 'Must be numeric');
                continue;
            }

            if ($key === 'Piso_Maximo' && (!is_int($val) && $val != (int)$val)) {
                $this->addError($key, 'Must be an integer');
            }

            if ($val < 0 && $key !== 'p_troncal') { // p_troncal might be 0 but usually not negative
                $this->addError($key, 'Cannot be negative');
            }
        }
    }

    private function validateEquipmentCatalog(): void
    {
        // Derivadores
        if (is_array($this->data['derivadores_data'])) {
            foreach ($this->data['derivadores_data'] as $model => $props) {
                if (!is_array($props)) {
                    $this->addError("derivadores_data.$model", 'Invalid entry structure');
                    continue;
                }
                
                $fields = ['derivacion', 'paso', 'salidas'];
                foreach ($fields as $f) {
                    if (!isset($props[$f]) || !is_numeric($props[$f])) {
                        $this->addError("derivadores_data.$model.$f", 'Missing or non-numeric value');
                    } elseif ($props[$f] < 0) {
                        $this->addError("derivadores_data.$model.$f", 'Value cannot be negative');
                    }
                }
            }
        }

        // Repartidores
        if (is_array($this->data['repartidores_data'])) {
            foreach ($this->data['repartidores_data'] as $model => $props) {
                if (!is_array($props)) {
                    $this->addError("repartidores_data.$model", 'Invalid entry structure');
                    continue;
                }

                $fields = ['perdida_insercion', 'salidas'];
                foreach ($fields as $f) {
                    if (!isset($props[$f]) || !is_numeric($props[$f])) {
                        $this->addError("repartidores_data.$model.$f", 'Missing or non-numeric value');
                    } elseif ($props[$f] < 0) {
                        $this->addError("repartidores_data.$model.$f", 'Value cannot be negative');
                    }
                }
            }
        }
    }

    private function validateTopology(): void
    {
        $map = $this->data['tus_requeridos_por_apartamento'];
        if (!is_array($map) || empty($map)) {
            $this->addError('tus_requeridos_por_apartamento', 'Map cannot be empty');
            return;
        }

        foreach ($map as $key => $tuCount) {
            if (!preg_match('/^\d+\|\d+$/', (string)$key)) {
                $this->addError('tus_requeridos_por_apartamento', "Invalid key format '$key'. Must match f|a");
                continue;
            }

            if (!is_int($tuCount) && $tuCount != (int)$tuCount) {
                $this->addError("tus_requeridos_por_apartamento.$key", 'TU count must be an integer');
            } elseif ($tuCount < 1) {
                $this->addError("tus_requeridos_por_apartamento.$key", 'TU count must be at least 1');
            }

            $parts = explode('|', $key);
            $floor = (int)$parts[0];
            $this->computedMaxFloor = max($this->computedMaxFloor, $floor);
            $this->apartmentSet[$key] = (int)$tuCount;
        }

        if ($this->data['Piso_Maximo'] != $this->computedMaxFloor) {
            $this->addError('Piso_Maximo', "Value ({$this->data['Piso_Maximo']}) does not match maximum floor in topology ({$this->computedMaxFloor})");
        }
    }

    private function validateCableMaps(): void
    {
        // 1. Derivador-Repartidor Map Set Equality
        $cableMap = $this->data['largo_cable_derivador_repartidor'];
        if (!is_array($cableMap)) {
            $this->addError('largo_cable_derivador_repartidor', 'Must be an array');
        } else {
            $diff1 = array_diff_key($this->apartmentSet, $cableMap);
            $diff2 = array_diff_key($cableMap, $this->apartmentSet);

            foreach ($diff1 as $missingKey => $val) {
                $this->addError('largo_cable_derivador_repartidor', "Missing entry for apartment '$missingKey'");
            }
            foreach ($diff2 as $extraKey => $val) {
                $this->addError('largo_cable_derivador_repartidor', "Unexpected entry for apartment '$extraKey'");
            }
        }

        // 2. TU Map Cardinality
        $tuCableMap = $this->data['largo_cable_tu'];
        if (!is_array($tuCableMap)) {
            $this->addError('largo_cable_tu', 'Must be an array');
            return;
        }

        foreach ($this->apartmentSet as $aptKey => $expectedTus) {
            $prefix = $aptKey . '|';
            $actualTus = [];

            foreach ($tuCableMap as $tuKey => $length) {
                if (strpos((string)$tuKey, $prefix) === 0) {
                    if (!preg_match('/^\d+\|\d+\|\d+$/', (string)$tuKey)) {
                        $this->addError('largo_cable_tu', "Invalid TU key format '$tuKey'");
                        continue;
                    }
                    
                    $parts = explode('|', $tuKey);
                    $index = (int)$parts[2];
                    $actualTus[] = $index;

                    if (!is_numeric($length) || $length < 0) {
                        $this->addError("largo_cable_tu.$tuKey", 'Invalid length');
                    }
                }
            }

            if (count($actualTus) !== $expectedTus) {
                $this->addError('largo_cable_tu', "Apartment '$aptKey' expects $expectedTus TUs but found " . count($actualTus));
            }

            sort($actualTus);
            for ($i = 1; $i <= $expectedTus; $i++) {
                if (!isset($actualTus[$i-1]) || $actualTus[$i-1] !== $i) {
                    $this->addError('largo_cable_tu', "Apartment '$aptKey' is missing TU index $i or indices are not sequential");
                    break;
                }
            }
        }
        
        // Check for orphan TUs (TUs in largo_cable_tu that don't belong to any apartment in apartmentSet)
        foreach ($tuCableMap as $tuKey => $length) {
            $parts = explode('|', $tuKey);
            if (count($parts) === 3) {
                $aptKey = $parts[0] . '|' . $parts[1];
                if (!isset($this->apartmentSet[$aptKey])) {
                    $this->addError('largo_cable_tu', "TU '$tuKey' belongs to an unknown apartment '$aptKey'");
                }
            } else {
                $this->addError('largo_cable_tu', "Malformed TU key '$tuKey'");
            }
        }
    }

    private function validateElectricalDomainRules(): void
    {
        if ($this->data['Nivel_minimo'] >= $this->data['Nivel_maximo']) {
            $this->addError('Nivel_minimo', 'Minimum level must be strictly less than maximum level');
        }

        $target = $this->data['Potencia_Objetivo_TU'];
        if ($target < $this->data['Nivel_minimo'] || $target > $this->data['Nivel_maximo']) {
            $this->addError('Potencia_Objetivo_TU', 'Target power must be between min and max levels');
        }

        if ($this->data['potencia_entrada'] <= 0) {
            $this->addError('potencia_entrada', 'Input power must be greater than zero');
        }

        // Additional attenuation checks if present
        $attenuationFields = [
            'atenuacion_cable_470mhz',
            'atenuacion_cable_698mhz',
            'atenuacion_cable_por_metro',
            'atenuacion_conector',
            'atenuacion_conexion_tu'
        ];

        foreach ($attenuationFields as $f) {
            if (isset($this->data[$f])) {
                if (!is_numeric($this->data[$f]) || $this->data[$f] < 0) {
                    $this->addError($f, 'Attenuation value must be numeric and non-negative');
                }
            }
        }
    }
}
