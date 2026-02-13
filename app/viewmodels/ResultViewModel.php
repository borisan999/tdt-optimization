<?php

declare(strict_types=1);

namespace app\viewmodels;

use DateTime;

class ResultViewModel
{
    /**
     * @param array $meta
     * @param array $summary
     * @param array $details
     * @param array $inputs
     * @param array $violations
     * @param array $warnings
     * @param string $status
     * @param string|null $created
     * @param int|null $dataset_id
     */
    public function __construct(
        public array $meta,
        public array $summary,
        public array $details,
        public array $inputs,
        public array $violations,
        public array $warnings,
        public string $status,
        public ?string $created,
        public ?int $dataset_id
    ) {}
}
