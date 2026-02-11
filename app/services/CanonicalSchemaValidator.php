<?php

namespace App\Services;

class CanonicalSchemaValidator
{
    private array $errors = [];

    public function validate(?array $canonical): bool
    {
        $this->errors = [];

        if (!is_array($canonical)) {
            $this->errors[] = "Canonical data is not an array.";
            return false;
        }

        // Root keys
        foreach (['inputs', 'apartments', 'tus'] as $key) {
            if (!array_key_exists($key, $canonical)) {
                $this->errors[] = "Missing root key: {$key}";
            }
        }

        if (!empty($this->errors)) {
            return false;
        }

        if (!is_array($canonical['inputs'])) {
            $this->errors[] = "'inputs' must be array.";
        }

        if (!is_array($canonical['apartments'])) {
            $this->errors[] = "'apartments' must be array.";
        }

        if (!is_array($canonical['tus'])) {
            $this->errors[] = "'tus' must be array.";
        }

        $this->validateApartments($canonical['apartments']);
        $this->validateTus($canonical['tus']);

        return empty($this->errors);
    }

    private function validateApartments(array $apartments): void
    {
        foreach ($apartments as $i => $apt) {
            if (!isset($apt['floor'], $apt['apartment'])) {
                $this->errors[] = "Apartment index {$i} missing floor/apartment.";
            }
        }
    }

    private function validateTus(array $tus): void
    {
        foreach ($tus as $i => $tu) {
            if (!isset($tu['floor'], $tu['apartment'], $tu['tu_index'])) {
                $this->errors[] = "TU index {$i} missing floor/apartment/tu_index.";
            }
        }
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
