<?php

declare(strict_types=1);

namespace App\Domain\Role\Repository;

use App\Domain\Role\Entity\ActionKey;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ActionKeyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActionKey::class);
    }

    public function findByKey(string $key): ?ActionKey
    {
        return $this->findOneBy(['key' => $key]);
    }
}
