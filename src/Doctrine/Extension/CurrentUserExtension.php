<?php

namespace App\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\User\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

class CurrentUserExtension implements QueryCollectionExtensionInterface
{
    public function __construct(private Security $security) {}

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        $this->addWhere($queryBuilder, $resourceClass);
    }

    private function addWhere(QueryBuilder $queryBuilder, string $resourceClass): void
    {
        if ($resourceClass !== PermitTravail::class) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        // Roles with bypass ownership see all permits
        if (
            $this->security->isGranted('ROLE_SUPER_ADMIN') ||
            $this->security->isGranted('ROLE_ADMIN') ||
            $this->security->isGranted('ROLE_HSE') ||
            $this->security->isGranted('ROLE_CHEF_PROJET')
        ) {
            return;
        }

        // Everyone else (ROLE_PRESTATAIRE, and prestataire-side roles such as
        // ROLE_CHEF_PROJET_PRESTATAIRE / ROLE_AGENT_TERRAIN) sees every permit
        // created by their own entreprise's team, not just their own permits
        // — aligned with PermitTravailVoter::hasOwnership() (creator OR same
        // entreprise as creator). If the user has no entreprise at all
        // (should not normally happen for these roles), fall back to the
        // strict per-user scope as a safe default.
        $rootAlias  = $queryBuilder->getRootAliases()[0];
        $entreprise = $user->getEntreprise();

        if ($entreprise !== null) {
            $queryBuilder
                ->join(sprintf('%s.createdBy', $rootAlias), 'cue_creator')
                ->andWhere('cue_creator.entreprise = :current_entreprise')
                ->setParameter('current_entreprise', $entreprise);

            return;
        }

        $queryBuilder
            ->andWhere(sprintf('%s.createdBy = :current_user', $rootAlias))
            ->setParameter('current_user', $user);
    }
}
