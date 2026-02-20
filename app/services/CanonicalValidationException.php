<?php

namespace App\Services;

class CanonicalValidationException extends \Exception
{
    protected array $errors;

    public function __construct(array $errors)
    {
        parent::__construct("Canonical validation failed");
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
