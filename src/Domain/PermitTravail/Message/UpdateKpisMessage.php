<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Message;

final class UpdateKpisMessage
{
    public function __construct(
        public readonly string $permitTravailId,
        public readonly string $type,
        public readonly string $decidedAt,
    ) {}
}
