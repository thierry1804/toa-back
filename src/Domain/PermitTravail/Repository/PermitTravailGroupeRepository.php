<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Repository;

use App\Domain\PermitTravail\Entity\PermitTravailGroupe;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PermitTravailGroupe>
 */
class PermitTravailGroupeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PermitTravailGroupe::class);
    }

    public function findByCodeSiteAndPlan(string $codeSite, ?PlanPrevention $planPrevention): ?PermitTravailGroupe
    {
        return $this->findOneBy([
            'codeSite'       => $codeSite,
            'planPrevention' => $planPrevention,
        ]);
    }
}
