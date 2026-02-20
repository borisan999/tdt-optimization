<?php

namespace App\Services;

class CanonicalNormalizer
{
    /**
     * Returns a new array with all associative keys sorted lexicographically
     * and numeric values normalized.
     * Numeric-indexed arrays (lists) are preserved in their original order.
     *
     * @param array $data
     * @return array
     */
    public static function normalize(array $data): array
    {
        $normalized = $data;
        self::recursiveSortAndNormalize($normalized);
        return $normalized;
    }

    /**
     * Recursively sorts associative arrays and normalizes numeric values.
     *
     * @param array $array
     */
    private static function recursiveSortAndNormalize(array &$array): void
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                self::recursiveSortAndNormalize($value);
            } elseif (is_float($value)) {
                // Remove float noise by formatting to a reasonably high precision
                // then converting back to numeric. This removes trailing .0 for integers.
                $formatted = number_format($value, 8, '.', '');
                $value = (float) rtrim(rtrim($formatted, '0'), '.');
                // Cast to int if mathematically an integer
                if ($value == (int)$value) {
                    $value = (int)$value;
                }
            }
        }

        if (self::isAssoc($array)) {
            ksort($array, SORT_STRING);
        }
    }

    /**
     * Detects if an array is associative.
     * An array is considered associative if its keys are not a sequential range from 0.
     *
     * @param array $array
     * @return bool
     */
    private static function isAssoc(array $array): bool
    {
        if ([] === $array) return false;
        return array_keys($array) !== range(0, count($array) - 1);
    }
}
