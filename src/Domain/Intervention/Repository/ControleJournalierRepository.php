<?php

declare(strict_types=1);

namespace App\Domain\Intervention\Repository;

use App\Domain\Intervention\Entity\ControleJournalier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ControleJournalier>
 */
class ControleJournalierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ControleJournalier::class);
    }

    public function existsByInterventionAndDate(string $interventionId, \DateTimeImmutable $date): bool
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.intervention = :interventionId')
            ->andWhere('c.date = :date')
            ->setParameter('interventionId', $interventionId)
            ->setParameter('date', $date);

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
