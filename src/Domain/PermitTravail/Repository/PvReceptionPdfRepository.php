<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Repository;

use App\Domain\PermitTravail\Entity\PvReceptionPdf;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PvReceptionPdf>
 */
class PvReceptionPdfRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PvReceptionPdf::class);
    }

    public function findByJobId(string $jobId): ?PvReceptionPdf
    {
        return $this->findOneBy(['jobId' => $jobId]);
    }

    public function findByPermitTravailId(string $permitTravailId): ?PvReceptionPdf
    {
        return $this->createQueryBuilder('p')
            ->join('p.permitTravail', 'pt')
            ->where('pt.id = :id')
            ->setParameter('id', $permitTravailId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
