<?php
namespace app\helpers;

use DateTime;
use Throwable;

class export_excel
{
    
    /**
     * Load TU level thresholds for a dataset
     * Source of truth: The inputs array (canonical JSON)
     */
    function loadTuLevelThresholds(array $inputs): array
    {
        return [
            'min'     => (float)($inputs['Nivel_minimo'] ?? 48.0),
            'max'     => (float)($inputs['Nivel_maximo'] ?? 69.0),
            'target'  => (float)($inputs['Potencia_Objetivo_TU'] ?? 58.0),
        ];
    }

    /**
     * Extract final TU level from one TU detail entry
     */
    function extractFinalTuLevel(array $tuDetail): ?float
    {
        return isset($tuDetail['Nivel TU Final (dBµV)'])
            ? (float)$tuDetail['Nivel TU Final (dBµV)']
            : null;
    }

    /**
     * Determine TU status based on thresholds
     */
    function computeTuStatus(
        ?float $finalLevel,
        ?float $minLevel,
        ?float $maxLevel
    ): string {
        if ($finalLevel === null || $minLevel === null || $maxLevel === null) {
            return 'N/A';
        }

        if ($finalLevel >= $minLevel && $finalLevel <= $maxLevel) {
            return 'OK';
        }

        return 'FAIL';
    }

    /**
     * Compute margin against target TU level
     */
    function computeTuMargin(?float $finalLevel, ?float $target): ?float
    {
        if ($finalLevel === null || $target === null) {
            return null;
        }

        return round($finalLevel - $target, 2);
    }

}