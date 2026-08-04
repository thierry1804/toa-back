<?php

declare(strict_types=1);

namespace App\Domain\Referentiel\Repository;

use App\Domain\Referentiel\Entity\ImportKmzSite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ImportKmzSite>
 */
class ImportKmzSiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImportKmzSite::class);
    }
}
