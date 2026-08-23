<?php

declare(strict_types=1);

namespace App\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Domain\Entreprise\Entity\Entreprise;
use App\Domain\User\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

class EntrepriseCollectionExtension implements QueryCollectionExtensionInterface
{
    public function __construct(private readonly Security $security) {}

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if ($resourceClass !== Entreprise::class) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        if (
            $this->security->isGranted('ROLE_SUPER_ADMIN')
            || $this->security->isGranted('ROLE_ADMIN')
            || $this->security->isGranted('ROLE_HSE')
            || $this->security->isGranted('ROLE_CHEF_PROJET')
        ) {
            return;
        }

        if (!$this->security->isGranted('ROLE_PRESTATAIRE')) {
            return;
        }

        $entreprise = $user->getEntreprise();
        $rootAlias = $queryBuilder->getRootAliases()[0];

        if ($entreprise === null) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        $queryBuilder
            ->andWhere(sprintf('%s = :current_entreprise', $rootAlias))
            ->setParameter('current_entreprise', $entreprise);
    }
}
