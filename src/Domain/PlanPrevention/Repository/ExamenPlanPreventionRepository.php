<?php

declare(strict_types=1);

namespace App\Domain\PlanPrevention\Repository;

use App\Domain\PlanPrevention\Entity\ExamenPlanPrevention;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExamenPlanPrevention>
 */
class ExamenPlanPreventionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExamenPlanPrevention::class);
    }
}
