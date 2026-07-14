<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Repository;

use App\Domain\PermitTravail\Entity\PermitTravailPdf;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PermitTravailPdf>
 */
class PermitTravailPdfRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PermitTravailPdf::class);
    }

    public function findByJobId(string $jobId): ?PermitTravailPdf
    {
        return $this->findOneBy(['jobId' => $jobId]);
    }

    public function findByPermitTravailId(string $permitTravailId): ?PermitTravailPdf
    {
        return $this->createQueryBuilder('p')
            ->join('p.permitTravail', 'pt')
            ->where('pt.id = :id')
            ->setParameter('id', $permitTravailId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
