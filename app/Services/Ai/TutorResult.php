<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\Recording;

final readonly class TutorResult
{
    public function __construct(
        public string $transcript,
        public string $response,
        public Recording $recording,
    ) {}
}
