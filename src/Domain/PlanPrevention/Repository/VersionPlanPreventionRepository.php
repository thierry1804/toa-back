<?php

declare(strict_types=1);

namespace App\Domain\PlanPrevention\Repository;

use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Entity\VersionPlanPrevention;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VersionPlanPrevention>
 */
class VersionPlanPreventionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VersionPlanPrevention::class);
    }

    public function findNextNumeroVersion(PlanPrevention $plan): int
    {
        $result = $this->createQueryBuilder('v')
            ->select('MAX(v.numeroVersion)')
            ->where('v.planPrevention = :plan')
            ->setParameter('plan', $plan)
            ->getQuery()
            ->getSingleScalarResult();

        return ($result === null ? 0 : (int) $result) + 1;
    }
}
