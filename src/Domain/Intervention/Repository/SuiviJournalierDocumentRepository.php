<?php

declare(strict_types=1);

namespace App\Domain\Intervention\Repository;

use App\Domain\Intervention\Entity\SuiviJournalierDocument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SuiviJournalierDocument>
 */
class SuiviJournalierDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SuiviJournalierDocument::class);
    }
}
