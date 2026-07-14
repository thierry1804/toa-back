<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Repository;

use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Entity\PermitTravailLog;
use App\Domain\PermitTravail\Enum\ActionPermitTravailLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PermitTravailLog>
 */
class PermitTravailLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PermitTravailLog::class);
    }

    /** @return PermitTravailLog[] */
    public function findByPermitTravailAndDate(PermitTravail $permit, \DateTimeImmutable $date): array
    {
        $start = $date->setTime(0, 0, 0);
        $end   = $date->setTime(23, 59, 59);

        return $this->createQueryBuilder('l')
            ->where('l.permitTravail = :permit')
            ->andWhere('l.createdAt >= :start')
            ->andWhere('l.createdAt <= :end')
            ->setParameter('permit', $permit)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return PermitTravailLog[] */
    public function findRecentByCodeSite(string $codeSite, int $limit = 10): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.codeSite = :codeSite')
            ->setParameter('codeSite', $codeSite)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return PermitTravailLog[] */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('l')
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByActionAndDate(
        ActionPermitTravailLog $action,
        \DateTimeImmutable $date,
        ?string $codeSite = null,
        ?string $typePermis = null,
    ): int {
        $start = $date->setTime(0, 0, 0);
        $end   = $date->setTime(23, 59, 59);

        $qb = $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.action = :action')
            ->andWhere('l.createdAt >= :start')
            ->andWhere('l.createdAt <= :end')
            ->setParameter('action', $action->value)
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        if ($codeSite !== null) {
            $qb->andWhere('l.codeSite = :codeSite')->setParameter('codeSite', $codeSite);
        }

        if ($typePermis !== null) {
            $qb->andWhere('l.typePermis = :typePermis')->setParameter('typePermis', $typePermis);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
