<?php

declare(strict_types=1);

namespace App\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Domain\ActivityPlanning\Entity\ActivityPlanning;
use App\Domain\User\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

class ActivityPlanningExtension implements QueryCollectionExtensionInterface
{
    private const BYPASS_ROLES = [
        'ROLE_SUPER_ADMIN',
        'ROLE_ADMIN',
        'ROLE_HSE',
        'ROLE_DG',
    ];

    public function __construct(private readonly Security $security) {}

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        Operation $operation = null,
        array $context = [],
    ): void {
        if ($resourceClass !== ActivityPlanning::class) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        foreach (self::BYPASS_ROLES as $role) {
            if ($this->security->isGranted($role)) {
                return;
            }
        }

        $root = $queryBuilder->getRootAliases()[0];

        // Un planning est créé par le chef de projet TOA, jamais par le
        // prestataire (R-04) : createdBy ne vaut donc jamais l'utilisateur
        // côté prestataire et la collection restait vide. Ces profils
        // doivent voir tous les plannings de leur entreprise (champ
        // `entreprise` de ActivityPlanning = prestataire assigné au
        // planning), quel que soit le créateur.
        $entreprise = $user->getEntreprise();
        if ($entreprise !== null) {
            $queryBuilder
                ->andWhere(sprintf('%s.entreprise = :ap_current_entreprise', $root))
                ->setParameter('ap_current_entreprise', $entreprise);

            return;
        }

        // Repli : utilisateur sans entreprise (cas anormal hors
        // BYPASS_ROLES). On conserve l'ancien scope par créateur plutôt que
        // d'exposer toute la collection.
        $queryBuilder
            ->andWhere(sprintf('%s.createdBy = :ap_current_user', $root))
            ->setParameter('ap_current_user', $user);
    }
}
