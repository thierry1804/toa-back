<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\MessageHandler;

use App\Domain\PermitTravail\Message\CheckPermitsExpirationMessage;
use App\Domain\PermitTravail\Repository\PermitTravailRepository;
use App\Domain\PermitTravail\Service\PermitTravailNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CheckPermitsExpirationHandler
{
    public function __construct(
        private readonly PermitTravailRepository $permitRepository,
        private readonly PermitTravailNotificationService $notificationService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly int $seuilExpirationJours = 3,
    ) {
    }

    public function __invoke(CheckPermitsExpirationMessage $message): void
    {
        $now = new \DateTimeImmutable();
        $seuil = $now->modify(sprintf('+%d days', $this->seuilExpirationJours));

        $permits = $this->permitRepository->findExpirantSansNotification($now, $seuil);

        if (empty($permits)) {
            return;
        }

        foreach ($permits as $permit) {
            $this->notificationService->notifierExpirationProchaine($permit);
            $permit->setNotificationExpirationEnvoyeeAt($now);
        }

        $this->entityManager->flush();

        $this->logger->info('[PermitTravail] Vérification des expirations effectuée', [
            'nb_permits_notifies' => count($permits),
        ]);
    }
}
