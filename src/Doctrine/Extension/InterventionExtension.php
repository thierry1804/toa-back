<?php

declare(strict_types=1);

namespace App\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Domain\Intervention\Entity\Intervention;
use App\Domain\User\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

class InterventionExtension implements QueryCollectionExtensionInterface
{
    public function __construct(private readonly Security $security) {}

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        Operation $operation = null,
        array $context = [],
    ): void {
        if ($resourceClass !== Intervention::class) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        // Admins/HSE/Chef de projet voient tout
        if ($this->security->isGranted('ROLE_HSE')
            || $this->security->isGranted('ROLE_ADMIN')
            || $this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return;
        }

        $root = $queryBuilder->getRootAliases()[0];

        // Prestataire/agent terrain : voir les interventions créées par eux, liées à
        // leur permis, OU appartenant à un collègue de la même entreprise (équipe)
        $queryBuilder
            ->leftJoin(sprintf('%s.createdBy', $root), 'int_creator')
            ->leftJoin(sprintf('%s.permitTravail', $root), 'int_permit')
            ->leftJoin('int_permit.createdBy', 'permit_creator');

        $ownershipConditions = [
            'int_creator.email = :int_current_user',
            'permit_creator.email = :int_current_user',
        ];

        $entreprise = $user->getEntreprise();
        if ($entreprise !== null) {
            $ownershipConditions[] = 'int_creator.entreprise = :int_current_entreprise';
            $ownershipConditions[] = 'permit_creator.entreprise = :int_current_entreprise';
            $queryBuilder->setParameter('int_current_entreprise', $entreprise->getId());
        }

        $queryBuilder
            ->andWhere($queryBuilder->expr()->orX(...$ownershipConditions))
            ->setParameter('int_current_user', $user->getUserIdentifier());
    }
}
