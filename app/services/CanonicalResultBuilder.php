<?php

namespace app\services;

use RuntimeException;

class CanonicalResultBuilder
{
    public static function build(
        array $rawTUs,
        float $nivelMin,
        float $nivelMax
    ): array {

        $canonical = [];

        foreach ($rawTUs as $index => $tu) {

            if (!isset(
                $tu['tu_id'],
                $tu['piso'],
                $tu['apto'],
                $tu['bloque'],
                $tu['nivel_tu'],
                $tu['losses']
            )) {
                throw new RuntimeException(
                    "Optimizer produced invalid TU at index {$index}"
                );
            }

            $nivel = (float)$tu['nivel_tu'];

            $canonical[] = [
                'tu_id'     => (string)$tu['tu_id'],
                'piso'      => (int)$tu['piso'],
                'apto'      => (int)$tu['apto'],
                'bloque'    => (int)$tu['bloque'],
                'nivel_tu'  => $nivel,
                'nivel_min' => $nivelMin,
                'nivel_max' => $nivelMax,
                'cumple'    => ($nivel >= $nivelMin && $nivel <= $nivelMax),
                'losses'    => self::normalizeLosses($tu['losses'])
            ];
        }

        self::validate($canonical);

        return $canonical;
    }

    private static function normalizeLosses(array $losses): array
    {
        $normalized = [];

        foreach ($losses as $loss) {

            if (!isset($loss['segment'], $loss['value'])) {
                throw new RuntimeException("Invalid loss segment format");
            }

            $normalized[] = [
                'segment' => (string)$loss['segment'],
                'value'   => (float)$loss['value']
            ];
        }

        return $normalized;
    }

    private static function validate(array $detail): void
    {
        if (empty($detail)) {
            throw new RuntimeException("Canonical detail cannot be empty");
        }

        foreach ($detail as $i => $tu) {
            $required = [
                'tu_id','piso','apto','bloque',
                'nivel_tu','nivel_min','nivel_max',
                'cumple','losses'
            ];

            foreach ($required as $key) {
                if (!array_key_exists($key, $tu)) {
                    throw new RuntimeException(
                        "Canonical validation failed: missing {$key} at TU {$i}"
                    );
                }
            }

            if (!is_array($tu['losses'])) {
                throw new RuntimeException("Losses must be array (TU {$i})");
            }
        }
    }
}
