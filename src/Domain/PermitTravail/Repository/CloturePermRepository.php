<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Repository;

use App\Domain\PermitTravail\Entity\CloturePerm;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CloturePerm>
 */
class CloturePermRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CloturePerm::class);
    }

    public function findByPermitTravailId(string $permitTravailId): ?CloturePerm
    {
        return $this->createQueryBuilder('c')
            ->join('c.permitTravail', 'pt')
            ->where('pt.id = :id')
            ->setParameter('id', $permitTravailId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
