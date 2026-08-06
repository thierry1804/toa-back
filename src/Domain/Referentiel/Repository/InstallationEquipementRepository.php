<?php

declare(strict_types=1);

namespace App\Domain\Referentiel\Repository;

use App\Domain\Referentiel\Entity\InstallationEquipement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InstallationEquipement>
 */
class InstallationEquipementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InstallationEquipement::class);
    }
}
