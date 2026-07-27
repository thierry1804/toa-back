<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Repository;

use App\Domain\PermitTravail\Entity\DecisionCdpPvReception;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DecisionCdpPvReception>
 */
class DecisionCdpPvReceptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DecisionCdpPvReception::class);
    }

    public function findLatestByPermitTravailId(string $permitTravailId): ?DecisionCdpPvReception
    {
        return $this->createQueryBuilder('d')
            ->where('d.permitTravail = :id')
            ->setParameter('id', $permitTravailId)
            ->orderBy('d.decidedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return DecisionCdpPvReception[] */
    public function findByPermitTravailId(string $permitTravailId): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.permitTravail = :id')
            ->setParameter('id', $permitTravailId)
            ->orderBy('d.decidedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
