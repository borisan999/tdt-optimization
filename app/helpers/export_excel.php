<?php
namespace app\helpers;

use DateTime;
use Throwable;

class export_excel
{
    
    /**
     * Load TU level thresholds for a dataset
     * Source of truth: parametros_generales
     */
    function loadTuLevelThresholds(PDO $pdo, int $datasetId): array
    {
        $sql = "
            SELECT param_name, param_value
            FROM parametros_generales
            WHERE dataset_id = :dataset_id
            AND param_name IN (
                'Nivel_Minimo_dBuV',
                'Nivel_Maximo_dBuV',
                'Potencia_Objetivo_TU_dBuV'
            )
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['dataset_id' => $datasetId]);

        $thresholds = [
            'min'     => null,
            'max'     => null,
            'target'  => null,
        ];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            switch ($row['param_name']) {
                case 'Nivel_Minimo_dBuV':
                    $thresholds['min'] = (float)$row['param_value'];
                    break;
                case 'Nivel_Maximo_dBuV':
                    $thresholds['max'] = (float)$row['param_value'];
                    break;
                case 'Potencia_Objetivo_TU_dBuV':
                    $thresholds['target'] = (float)$row['param_value'];
                    break;
            }
        }

        return $thresholds;
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