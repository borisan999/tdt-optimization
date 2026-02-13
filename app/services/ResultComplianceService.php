<?php

declare(strict_types=1);

namespace app\services;

class ResultComplianceService
{
    /**
     * @param array $details The canonical 'detail' array where each item is a TU.
     * @return array The list of TUs that have violations.
     */
    public static function calculateViolations(array $details): array
    {
        $violations = [];

        foreach ($details as $tu) {
            $nivel = (float)($tu['nivel_tu'] ?? 0);
            $min = (float)($tu['nivel_min'] ?? 48.0);
            $max = (float)($tu['nivel_max'] ?? 69.0);

            if ($nivel < $min) {
                $tu['_violation_type'] = 'LOW';
                $tu['_violation_delta'] = round($nivel - $min, 2);
                $violations[] = $tu;
            } elseif ($nivel > $max) {
                $tu['_violation_type'] = 'HIGH';
                $tu['_violation_delta'] = round($nivel - $max, 2);
                $violations[] = $tu;
            }
        }
        
        return $violations;
    }
}
