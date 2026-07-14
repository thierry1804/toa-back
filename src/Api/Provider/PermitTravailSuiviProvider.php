<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Resource\PermitTravailSuivi;
use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Enum\StatutPermitTravail;
use App\Domain\PermitTravail\Enum\TypePermitTravail;
use App\Domain\PermitTravail\Repository\PermitTravailLogRepository;
use Doctrine\ORM\EntityManagerInterface;

class PermitTravailSuiviProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PermitTravailLogRepository $logRepository,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PermitTravailSuivi
    {
        $request    = $context['request'] ?? null;
        $codeSite   = $request?->query->get('codeSite');
        $typePermis = $request?->query->get('typePermis');
        $statut     = $request?->query->get('statut');
        $dateDebut  = $this->parseDate($request?->query->get('dateDebut'));
        $dateFin    = $this->parseDate($request?->query->get('dateFin'));

        $typeEnum   = $typePermis !== null ? TypePermitTravail::tryFrom($typePermis) : null;
        $statutEnum = $statut !== null ? StatutPermitTravail::tryFrom($statut) : null;

        $output = new PermitTravailSuivi();

        $em = $this->entityManager;

        // Total count with filters
        $qb = $em->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(PermitTravail::class, 'p');
        $this->applyFilters($qb, 'p', $codeSite, $typeEnum, $statutEnum, $dateDebut, $dateFin);
        $output->totalPermis = (int) $qb->getQuery()->getSingleScalarResult();

        // Per statut
        $qb = $em->createQueryBuilder()
            ->select('p.statut, COUNT(p.id) as nb')
            ->from(PermitTravail::class, 'p')
            ->groupBy('p.statut');
        $this->applyFilters($qb, 'p', $codeSite, $typeEnum, null, $dateDebut, $dateFin);
        $parStatut = [];
        foreach ($qb->getQuery()->getResult() as $row) {
            $parStatut[$row['statut']->value] = (int) $row['nb'];
        }
        $output->parStatut = $parStatut;

        // Per type
        $qb = $em->createQueryBuilder()
            ->select('p.type, COUNT(p.id) as nb')
            ->from(PermitTravail::class, 'p')
            ->groupBy('p.type');
        $this->applyFilters($qb, 'p', $codeSite, null, $statutEnum, $dateDebut, $dateFin);
        $parType = [];
        foreach ($qb->getQuery()->getResult() as $row) {
            $parType[$row['type']->value] = (int) $row['nb'];
        }
        $output->parType = $parType;

        // Per site
        $qb = $em->createQueryBuilder()
            ->select('p.codeSite, COUNT(p.id) as nb')
            ->from(PermitTravail::class, 'p')
            ->groupBy('p.codeSite');
        $this->applyFilters($qb, 'p', null, $typeEnum, $statutEnum, $dateDebut, $dateFin);
        $parSite = [];
        foreach ($qb->getQuery()->getResult() as $row) {
            $parSite[$row['codeSite']] = (int) $row['nb'];
        }
        $output->parSite = $parSite;

        // Expiring within 7 days
        $now     = new \DateTimeImmutable();
        $in7days = $now->modify('+7 days');
        $qb = $em->createQueryBuilder()
            ->select('p')
            ->from(PermitTravail::class, 'p')
            ->where('p.dateFinPrevue >= :now')
            ->andWhere('p.dateFinPrevue <= :in7days')
            ->setParameter('now', $now)
            ->setParameter('in7days', $in7days)
            ->orderBy('p.dateFinPrevue', 'ASC');
        $this->applyFilters($qb, 'p', $codeSite, $typeEnum, $statutEnum);
        $output->expirantSous7j = $qb->getQuery()->getResult();

        // Recent logs
        $output->logsRecents = $codeSite !== null
            ? $this->logRepository->findRecentByCodeSite($codeSite)
            : $this->logRepository->findRecent();

        return $output;
    }

    private function applyFilters(
        \Doctrine\ORM\QueryBuilder $qb,
        string $alias,
        ?string $codeSite = null,
        ?TypePermitTravail $typePermis = null,
        ?StatutPermitTravail $statut = null,
        ?\DateTimeImmutable $dateDebut = null,
        ?\DateTimeImmutable $dateFin = null,
    ): void {
        if ($codeSite !== null && $codeSite !== '') {
            $qb->andWhere("$alias.codeSite = :codeSite")->setParameter('codeSite', $codeSite);
        }
        if ($typePermis !== null) {
            $qb->andWhere("$alias.type = :typePermis")->setParameter('typePermis', $typePermis->value);
        }
        if ($statut !== null) {
            $qb->andWhere("$alias.statut = :statut")->setParameter('statut', $statut->value);
        }
        if ($dateDebut !== null) {
            $qb->andWhere("$alias.createdAt >= :dateDebut")->setParameter('dateDebut', $dateDebut);
        }
        if ($dateFin !== null) {
            $qb->andWhere("$alias.createdAt <= :dateFin")->setParameter('dateFin', $dateFin->setTime(23, 59, 59));
        }
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
