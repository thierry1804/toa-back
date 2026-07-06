<?php

declare(strict_types=1);

namespace App\Domain\PlanPrevention\Repository;

use App\Domain\PlanPrevention\Entity\DecisionHsePlanPrevention;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DecisionHsePlanPrevention>
 */
class DecisionHsePlanPreventionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DecisionHsePlanPrevention::class);
    }
}
