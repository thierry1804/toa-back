<?php

namespace App\Domain\ActivityPlanning\MessageHandler;

use App\Domain\ActivityPlanning\Message\ActivityPlanningCreatedNotification;
use App\Domain\ActivityPlanning\Service\PlanningMailer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ActivityPlanningCreatedNotificationHandler
{
    public function __construct(
        private PlanningMailer $mailer,
    ) {
    }

    public function __invoke(ActivityPlanningCreatedNotification $message): void
    {
        $this->mailer->sendCreationNotification(
            recipientEmail: $message->providerEmail,
            recipientName: $message->providerName,
            planningId: $message->planningId,
            process: $message->process,
            siteCode: $message->siteCode,
            siteName: $message->siteName,
        );
    }
}
