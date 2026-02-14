<?php

declare(strict_types=1);

namespace app\viewmodels;

use DateTime;

class ResultViewModel
{
    public array $details;
    public array $summary;
    public array $inputs;
    public array $meta;
    public array $violations;
    public array $results;

    public function __construct(
        ?array $details,
        ?array $summary,
        ?array $inputs,
        ?array $meta,
        ?array $violations,
        ?array $results
    ) {
        $this->details = $details ?? [];
        $this->summary = $summary ?? [];
        $this->inputs = $inputs ?? [];
        $this->meta = $meta ?? [];
        $this->violations = $violations ?? [];
        $this->results = $results ?? [];
    }
}
