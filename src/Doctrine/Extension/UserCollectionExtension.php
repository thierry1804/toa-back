<?php

declare(strict_types=1);

namespace App\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Domain\User\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

class UserCollectionExtension implements QueryCollectionExtensionInterface
{
    public function __construct(private readonly Security $security) {}

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if ($resourceClass !== User::class) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        if (
            $this->security->isGranted('ROLE_SUPER_ADMIN')
            || $this->security->isGranted('ROLE_ADMIN')
        ) {
            return;
        }

        if (!$this->security->isGranted('ROLE_PRESTATAIRE')) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $entreprise = $user->getEntreprise();

        if ($entreprise === null) {
            $queryBuilder
                ->andWhere(sprintf('%s = :current_user', $rootAlias))
                ->setParameter('current_user', $user);

            return;
        }

        $queryBuilder
            ->andWhere(sprintf('%s.entreprise = :current_entreprise', $rootAlias))
            ->setParameter('current_entreprise', $entreprise);
    }
}
