<?php

declare(strict_types=1);

namespace App\Domain\Referentiel\Repository;

use App\Domain\Referentiel\Entity\CategorieRisque;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CategorieRisque>
 */
class CategorieRisqueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CategorieRisque::class);
    }
}
