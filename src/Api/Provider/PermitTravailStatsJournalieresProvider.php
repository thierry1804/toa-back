<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Resource\PermitTravailStatsJournalieres;
use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Enum\ActionPermitTravailLog;
use App\Domain\PermitTravail\Enum\StatutPermitTravail;
use App\Domain\PermitTravail\Repository\PermitTravailLogRepository;
use Doctrine\ORM\EntityManagerInterface;

class PermitTravailStatsJournalieresProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PermitTravailLogRepository $logRepository,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PermitTravailStatsJournalieres
    {
        $request    = $context['request'] ?? null;
        $codeSite   = $request?->query->get('codeSite');
        $typePermis = $request?->query->get('typePermis');

        $dateStr = $request?->query->get('date');
        $date    = null;
        if ($dateStr !== null && $dateStr !== '') {
            try {
                $date = new \DateTimeImmutable($dateStr);
            } catch (\Throwable) {
                $date = null;
            }
        }
        if ($date === null) {
            $date = new \DateTimeImmutable('today');
        }

        $output = new PermitTravailStatsJournalieres();

        $output->nbConsultations = $this->logRepository->countByActionAndDate(
            ActionPermitTravailLog::CONSULTE,
            $date,
            $codeSite,
            $typePermis,
        );

        $output->nbChangementsStatut = $this->logRepository->countByActionAndDate(
            ActionPermitTravailLog::STATUT_CHANGE,
            $date,
            $codeSite,
            $typePermis,
        );

        $output->nbTelechargements = $this->logRepository->countByActionAndDate(
            ActionPermitTravailLog::DOCUMENT_TELECHARGE,
            $date,
            $codeSite,
            $typePermis,
        ) + $this->logRepository->countByActionAndDate(
            ActionPermitTravailLog::PDF_GENERE,
            $date,
            $codeSite,
            $typePermis,
        );

        $output->permisActifs = $this->countPermisActifs($codeSite, $typePermis);

        return $output;
    }

    private function countPermisActifs(?string $codeSite, ?string $typePermis): int
    {
        // Active = non BROUILLON, non REJETE, non REFUSE_HSE
        $qb = $this->entityManager->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(PermitTravail::class, 'p')
            ->where('p.statut NOT IN (:inactifs)')
            ->setParameter('inactifs', [
                StatutPermitTravail::BROUILLON->value,
                StatutPermitTravail::REJETE->value,
                StatutPermitTravail::REFUSE_HSE->value,
            ]);

        if ($codeSite !== null && $codeSite !== '') {
            $qb->andWhere('p.codeSite = :codeSite')->setParameter('codeSite', $codeSite);
        }
        if ($typePermis !== null && $typePermis !== '') {
            $qb->andWhere('p.type = :typePermis')->setParameter('typePermis', $typePermis);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
