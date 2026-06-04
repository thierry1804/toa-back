<?php

namespace App\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Domain\Permit\Entity\Permit;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use App\Domain\User\Entity\User;

class CurrentUserExtension implements QueryCollectionExtensionInterface
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        $this->addWhere($queryBuilder, $resourceClass);
    }

    private function addWhere(QueryBuilder $queryBuilder, string $resourceClass): void
    {
        if (Permit::class !== $resourceClass && !is_subclass_of($resourceClass, Permit::class)) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return; // Anonymous users should probably see nothing, but access_control handles auth.
        }

        // Users with these roles can see all permits
        if ($this->security->isGranted('ROLE_CHEF_PROJET') || 
            $this->security->isGranted('ROLE_COLLABORATEUR') || 
            $this->security->isGranted('ROLE_HSE') || 
            $this->security->isGranted('ROLE_DIRECTION') || 
            $this->security->isGranted('ROLE_ADMIN') || 
            $this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return;
        }

        // Prestatiare is limited to their own permits
        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder->andWhere(sprintf('%s.creerPar = :current_user', $rootAlias));
        $queryBuilder->setParameter('current_user', $user->getUserIdentifier());
    }
}
