<?php

declare(strict_types=1);

namespace App\Domain\Menu\Repository;

use App\Domain\Menu\Entity\MenuAccess;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MenuAccessRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MenuAccess::class);
    }

    /** @return MenuAccess[] */
    public function findByRolesAndRoute(array $roles, string $route): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.menu', 'm')
            ->where('a.role IN (:roles)')
            ->andWhere('m.route = :route')
            ->andWhere('m.isActive = :active')
            ->setParameter('roles', $roles)
            ->setParameter('route', $route)
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }

    public function routeHasAccessRules(string $route): bool
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->join('a.menu', 'm')
            ->where('m.route = :route')
            ->setParameter('route', $route)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}
