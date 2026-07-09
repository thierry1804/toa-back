<?php

declare(strict_types=1);

namespace App\Domain\PlanPrevention\Repository;

use App\Domain\PlanPrevention\Entity\PlanPreventionPdf;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlanPreventionPdf>
 */
class PlanPreventionPdfRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlanPreventionPdf::class);
    }

    public function findByJobId(string $jobId): ?PlanPreventionPdf
    {
        return $this->findOneBy(['jobId' => $jobId]);
    }

    public function findByPlanPreventionId(string $planPreventionId): ?PlanPreventionPdf
    {
        return $this->createQueryBuilder('p')
            ->join('p.planPrevention', 'pp')
            ->where('pp.id = :id')
            ->setParameter('id', $planPreventionId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
