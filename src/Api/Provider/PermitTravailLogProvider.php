<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Repository\PermitTravailLogRepository;
use Doctrine\ORM\EntityManagerInterface;

class PermitTravailLogProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PermitTravailLogRepository $logRepository,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $permitTravailId = $uriVariables['permitTravailId'] ?? null;
        if ($permitTravailId === null) {
            return [];
        }

        $permit = $this->entityManager->find(PermitTravail::class, $permitTravailId);
        if (!$permit instanceof PermitTravail) {
            return [];
        }

        $request = $context['request'] ?? null;
        $date    = null;

        if ($request !== null) {
            $dateStr = $request->query->get('date');
            if ($dateStr !== null && $dateStr !== '') {
                try {
                    $date = new \DateTimeImmutable($dateStr);
                } catch (\Throwable) {
                    $date = null;
                }
            }
        }

        if ($date === null) {
            $date = new \DateTimeImmutable('today');
        }

        return $this->logRepository->findByPermitTravailAndDate($permit, $date);
    }
}
