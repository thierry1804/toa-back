<?php

declare(strict_types=1);

namespace App\Domain\Intervention\Repository;

use App\Domain\Intervention\Entity\EvaluationRisque;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EvaluationRisque>
 */
class EvaluationRisqueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EvaluationRisque::class);
    }

    /** @return EvaluationRisque[] */
    public function findNonReevaluesByIntervention(string $interventionId): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.intervention = :id')
            ->andWhere('e.estReevalue = false')
            ->setParameter('id', $interventionId)
            ->getQuery()
            ->getResult();
    }
}
