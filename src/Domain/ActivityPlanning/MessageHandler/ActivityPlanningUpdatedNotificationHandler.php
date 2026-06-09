<?php

namespace App\Domain\ActivityPlanning\MessageHandler;

use App\Domain\ActivityPlanning\Message\ActivityPlanningUpdatedNotification;
use App\Domain\ActivityPlanning\Service\PlanningMailer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ActivityPlanningUpdatedNotificationHandler
{
    public function __construct(
        private PlanningMailer $mailer,
    ) {
    }

    public function __invoke(ActivityPlanningUpdatedNotification $message): void
    {
        $this->mailer->sendModificationNotification(
            recipientEmail: $message->providerEmail,
            recipientName: $message->providerName,
            planningId: $message->planningId,
            process: $message->process,
            changes: $message->changes,
        );
    }
}
