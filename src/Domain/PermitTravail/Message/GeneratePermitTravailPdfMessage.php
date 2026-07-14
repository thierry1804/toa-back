<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Message;

final class GeneratePermitTravailPdfMessage
{
    public function __construct(
        public readonly string $permitTravailId,
        public readonly string $jobId,
        public readonly int    $requesterId,
    ) {}
}
