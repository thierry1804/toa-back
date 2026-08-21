<?php

namespace App\Domain\ActivityPlanning\Message;

class ActivityPlanningCreatedNotification
{
    public function __construct(
        public readonly int $planningId,
        public readonly string $providerEmail,
        public readonly string $providerName,
        public readonly string $process,
        public readonly string $siteCode,
        public readonly string $siteName,
    ) {
    }
}
