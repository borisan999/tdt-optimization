<?php

declare(strict_types=1);

namespace app\services;

use DomainException;

final class ResultComplianceService
{
    /**
     * Calculate compliance violations for canonical TUs.
     *
     * @param array<int, array<string,mixed>> $details The canonical 'detail' array where each item is a TU.
     * @return array<int, array<string,mixed>> The list of TUs that have violations. Each TU copy is returned (immutable).
     *
     * @throws DomainException If a TU is malformed.
     */
    public static function calculateViolations(array $details): array
    {
        $violations = [];

        foreach ($details as $index => $tu) {
            if (!is_array($tu)) {
                throw new DomainException("TU at index {$index} must be an array.");
            }

            // Required numeric fields
            $numericKeys = ['nivel_tu', 'nivel_min', 'nivel_max'];
            foreach ($numericKeys as $key) {
                if (!isset($tu[$key]) || !is_numeric($tu[$key])) {
                    throw new DomainException("TU at index {$index} is missing numeric field '{$key}' or it's invalid.");
                }
            }

            $nivel = (float)$tu['nivel_tu'];
            $min   = (float)$tu['nivel_min'];
            $max   = (float)$tu['nivel_max'];

            // Create a copy to preserve immutability
            $tuCopy = $tu;

            if ($nivel < $min) {
                $tuCopy['_violation_type']  = 'LOW';
                $tuCopy['_violation_delta'] = round($nivel - $min, 2);
                $violations[] = $tuCopy;
            } elseif ($nivel > $max) {
                $tuCopy['_violation_type']  = 'HIGH';
                $tuCopy['_violation_delta'] = round($nivel - $max, 2);
                $violations[] = $tuCopy;
            }
        }

        return $violations;
    }
}
