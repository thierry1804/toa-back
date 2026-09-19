<?php

declare(strict_types=1);

namespace App\Api\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Domain\ActivityPlanning\Entity\ActivityPlanning;
use App\Domain\Intervention\Entity\Intervention;
use App\Domain\Intervention\Entity\SuiviJournalier;
use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\User\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Restricts collection and item queries for ROLE_HSE users to their own entreprise,
 * except HSE of an internal (TOA) entreprise who see every prestataire (R-13).
 * ROLE_ADMIN and ROLE_SUPER_ADMIN bypass this filter entirely.
 */
class EntrepriseScopeExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    private const SCOPED_ENTITIES = [
        PlanPrevention::class,
        PermitTravail::class,
        ActivityPlanning::class,
        Intervention::class,
        SuiviJournalier::class,
    ];

    public function __construct(private readonly Security $security) {}

    public function applyToCollection(QueryBuilder $qb, QueryNameGeneratorInterface $nameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->apply($qb, $nameGenerator, $resourceClass);
    }

    public function applyToItem(QueryBuilder $qb, QueryNameGeneratorInterface $nameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        $this->apply($qb, $nameGenerator, $resourceClass);
    }

    private function apply(QueryBuilder $qb, QueryNameGeneratorInterface $nameGenerator, string $resourceClass): void
    {
        if (!in_array($resourceClass, self::SCOPED_ENTITIES, true)) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        if (!in_array('ROLE_HSE', $user->getRoles(), true)) {
            return;
        }

        $entreprise = $user->getEntreprise();
        // Le HSE de TOA (entreprise interne) valide les plans/permis de tous les prestataires (R-13).
        if ($entreprise === null || $entreprise->isInterne()) {
            return;
        }

        $alias     = $qb->getRootAliases()[0];
        $entrepriseId = $entreprise->getId();

        match ($resourceClass) {
            ActivityPlanning::class => $this->applyByEntrepriseId($qb, $nameGenerator, $alias, $entrepriseId),
            default                 => $this->applyByCreatedByEntreprise($qb, $nameGenerator, $alias, $entrepriseId),
        };
    }

    private function applyByEntrepriseId(QueryBuilder $qb, QueryNameGeneratorInterface $nameGenerator, string $alias, mixed $entrepriseId): void
    {
        $param = $nameGenerator->generateParameterName('hseEntrepriseId');
        $qb->andWhere("$alias.entreprise = :$param")
           ->setParameter($param, $entrepriseId);
    }

    private function applyByCreatedByEntreprise(QueryBuilder $qb, QueryNameGeneratorInterface $nameGenerator, string $alias, mixed $entrepriseId): void
    {
        $createdByAlias  = $nameGenerator->generateJoinAlias('createdBy');
        $param           = $nameGenerator->generateParameterName('hseEntrepriseId');

        $qb->innerJoin("$alias.createdBy", $createdByAlias)
           ->andWhere("$createdByAlias.entreprise = :$param")
           ->setParameter($param, $entrepriseId);
    }
}
