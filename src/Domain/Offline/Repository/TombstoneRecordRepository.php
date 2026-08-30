<?php

declare(strict_types=1);

namespace App\Domain\Offline\Repository;

use App\Domain\Offline\Entity\TombstoneRecord;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TombstoneRecord>
 */
class TombstoneRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TombstoneRecord::class);
    }

    /**
     * @return list<string> ids (as string) des entités de ce type supprimées depuis $since
     */
    public function findDeletedIdsSince(string $entityType, \DateTimeImmutable $since): array
    {
        $rows = $this->createQueryBuilder('t')
            ->select('t.entityId')
            ->andWhere('t.entityType = :type')
            ->andWhere('t.deletedAt >= :since')
            ->setParameter('type', $entityType)
            ->setParameter('since', $since)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): string => $row['entityId'], $rows);
    }
}
