<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Repository;

use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Enum\StatutPermitTravail;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PermitTravail>
 */
class PermitTravailRepository extends ServiceEntityRepository
{
    private const STATUTS_ACTIFS = [
        StatutPermitTravail::VALIDE,
        StatutPermitTravail::VALIDE_HSE,
        StatutPermitTravail::EN_COURS,
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PermitTravail::class);
    }

    /**
     * Active permits whose dateFinPrevue falls within [now, $seuil] and for
     * which no expiration warning has been sent yet.
     *
     * @return PermitTravail[]
     */
    public function findExpirantSansNotification(\DateTimeImmutable $now, \DateTimeImmutable $seuil): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.statut IN (:statuts)')
            ->andWhere('p.dateFinPrevue IS NOT NULL')
            ->andWhere('p.dateFinPrevue BETWEEN :now AND :seuil')
            ->andWhere('p.notificationExpirationEnvoyeeAt IS NULL')
            ->setParameter('statuts', self::STATUTS_ACTIFS)
            ->setParameter('now', $now)
            ->setParameter('seuil', $seuil)
            ->getQuery()
            ->getResult();
    }
}
