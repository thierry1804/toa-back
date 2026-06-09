<?php

namespace App\Domain\ActivityPlanning\Service;

use App\Domain\ActivityPlanning\Entity\ActivityPlanning;
use Doctrine\ORM\EntityManagerInterface;

class ConflictDetector
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function hasConflict(ActivityPlanning $planning): bool
    {
        $siteCode = $planning->getSiteCode();
        $newStart = $planning->getExpectedStartDate();
        $newEnd = $planning->getExpectedEndDate();

        if (null === $siteCode || null === $newStart || null === $newEnd) {
            return false;
        }

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(p.id)')
            ->from(ActivityPlanning::class, 'p')
            ->where('p.siteCode = :siteCode')
            ->andWhere('p.id != :currentId')
            ->andWhere('p.status IN (:activeStatuses)')
            ->andWhere('p.expectedStartDate < :newEnd')
            ->andWhere('p.expectedEndDate > :newStart')
            ->setParameter('siteCode', $siteCode)
            ->setParameter('currentId', $planning->getId() ?? 0)
            ->setParameter('activeStatuses', [
                ActivityPlanning::STATUS_EN_COURS,
                ActivityPlanning::STATUS_PLANIFIE,
            ])
            ->setParameter('newEnd', $newEnd)
            ->setParameter('newStart', $newStart);

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
