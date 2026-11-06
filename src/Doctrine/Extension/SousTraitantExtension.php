<?php

declare(strict_types=1);

namespace App\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Domain\Entreprise\Entity\SousTraitant;
use App\Domain\User\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

class SousTraitantExtension implements QueryCollectionExtensionInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        Operation $operation = null,
        array $context = [],
    ): void {
        if ($resourceClass !== SousTraitant::class) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        foreach (['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_HSE', 'ROLE_DIRECTION'] as $role) {
            if (in_array($role, $user->getRoles(), true)) {
                return;
            }
        }

        $roles = $user->getRoles();
        $isAgent = in_array('ROLE_AGENT_TERRAIN', $roles, true)
            && !in_array('ROLE_PRESTATAIRE', $roles, true)
            && !in_array('ROLE_CHEF_PROJET_PRESTATAIRE', $roles, true);
        $root = $queryBuilder->getRootAliases()[0];
        if ($isAgent) {
            if ($user->getSousTraitant() === null) {
                $queryBuilder->andWhere('1 = 0');

                return;
            }
            $queryBuilder
                ->andWhere(sprintf('%s = :own_sous_traitant', $root))
                ->setParameter('own_sous_traitant', $user->getSousTraitant());

            return;
        }

        $entreprise = $user->getEntreprise();
        if ($entreprise === null) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        $queryBuilder
            ->andWhere(sprintf('%s.entreprise = :st_entreprise', $root))
            ->setParameter('st_entreprise', $entreprise);
    }
}
