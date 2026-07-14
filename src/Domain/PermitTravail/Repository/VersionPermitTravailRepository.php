<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Repository;

use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Entity\VersionPermitTravail;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VersionPermitTravail>
 */
class VersionPermitTravailRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VersionPermitTravail::class);
    }

    public function findNextNumeroVersion(PermitTravail $permit): int
    {
        $result = $this->createQueryBuilder('v')
            ->select('MAX(v.numeroVersion)')
            ->where('v.permitTravail = :permit')
            ->setParameter('permit', $permit)
            ->getQuery()
            ->getSingleScalarResult();

        return ($result === null ? 0 : (int) $result) + 1;
    }

    /** @return VersionPermitTravail[] */
    public function findByPermit(PermitTravail $permit): array
    {
        return $this->createQueryBuilder('v')
            ->where('v.permitTravail = :permit')
            ->setParameter('permit', $permit)
            ->orderBy('v.numeroVersion', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
