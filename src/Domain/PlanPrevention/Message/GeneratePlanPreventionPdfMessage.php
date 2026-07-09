<?php

declare(strict_types=1);

namespace App\Domain\PlanPrevention\Message;

final class GeneratePlanPreventionPdfMessage
{
    public function __construct(
        public readonly string $planPreventionId,
        public readonly string $jobId,
        public readonly int    $requesterId,
    ) {}
}
