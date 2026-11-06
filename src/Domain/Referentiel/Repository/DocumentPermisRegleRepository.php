<?php

declare(strict_types=1);

namespace App\Domain\Referentiel\Repository;

use App\Domain\Referentiel\Entity\DocumentPermisRegle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<DocumentPermisRegle> */
class DocumentPermisRegleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentPermisRegle::class);
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return DocumentPermisRegle[] */
    public function findActives(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.actif = true')
            ->orderBy('r.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
