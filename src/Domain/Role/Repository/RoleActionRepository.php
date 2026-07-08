<?php

declare(strict_types=1);

namespace App\Domain\Role\Repository;

use App\Domain\Role\Entity\RoleAction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RoleActionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RoleAction::class);
    }

    public function existsByRolesAndAction(array $roles, string $actionKey): bool
    {
        return (int) $this->createQueryBuilder('ra')
            ->select('COUNT(ra.id)')
            ->where('ra.roleName IN (:roles)')
            ->andWhere('ra.actionKey = :key')
            ->setParameter('roles', $roles)
            ->setParameter('key', $actionKey)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /** @return RoleAction[] */
    public function findByRolesAndAction(array $roles, string $actionKey): array
    {
        return $this->createQueryBuilder('ra')
            ->where('ra.roleName IN (:roles)')
            ->andWhere('ra.actionKey = :key')
            ->setParameter('roles', $roles)
            ->setParameter('key', $actionKey)
            ->getQuery()
            ->getResult();
    }

    /** @return string[] */
    public function findActionKeysByRoles(array $roles): array
    {
        if (empty($roles)) {
            return [];
        }

        $results = $this->createQueryBuilder('ra')
            ->select('ra.actionKey')
            ->where('ra.roleName IN (:roles)')
            ->setParameter('roles', $roles)
            ->distinct()
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'actionKey');
    }
}
