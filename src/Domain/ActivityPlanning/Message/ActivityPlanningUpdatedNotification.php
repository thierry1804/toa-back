<?php

namespace App\Domain\ActivityPlanning\Message;

class ActivityPlanningUpdatedNotification
{
    public function __construct(
        public readonly int $planningId,
        public readonly string $providerEmail,
        public readonly string $providerName,
        public readonly string $process,
        public readonly array $changes,
    ) {
    }
}
