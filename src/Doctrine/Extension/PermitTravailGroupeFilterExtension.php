<?php

declare(strict_types=1);

namespace App\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Domain\PermitTravail\Entity\PermitTravail;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\Uuid;

/**
 * Filtre GET /api/permits-travail?groupeId={uuid}.
 *
 * `groupeId` n'est pas une colonne mappée sur PermitTravail : c'est un id
 * calculé (cf. PermitTravail::getGroupeId()) dérivé de la relation OneToOne
 * inverse vers PermitTravailGroupe (côté Général OU côté Spécialisé), donc
 * ApiFilter(SearchFilter::class) ne peut pas le cibler directement. On filtre
 * ici via un JOIN sur les deux côtés possibles du groupe.
 */
class PermitTravailGroupeFilterExtension implements QueryCollectionExtensionInterface
{
    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        Operation $operation = null,
        array $context = [],
    ): void {
        if ($resourceClass !== PermitTravail::class) {
            return;
        }

        $groupeId = $context['filters']['groupeId'] ?? null;
        if ($groupeId === null || $groupeId === '') {
            return;
        }

        $root = $queryBuilder->getRootAliases()[0];

        if (!is_string($groupeId) || !Uuid::isValid($groupeId)) {
            // UUID malformé : aucun résultat, jamais d'erreur 500 (comportement
            // identique à un groupe inexistant).
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        $generalAlias    = $queryNameGenerator->generateJoinAlias('groupeAsGeneral');
        $specialiseAlias = $queryNameGenerator->generateJoinAlias('groupeAsSpecialise');
        $paramName       = $queryNameGenerator->generateParameterName('groupeId');

        $queryBuilder
            ->leftJoin(sprintf('%s.groupeAsGeneral', $root), $generalAlias)
            ->leftJoin(sprintf('%s.groupeAsSpecialise', $root), $specialiseAlias)
            ->andWhere($queryBuilder->expr()->orX(
                sprintf('%s.id = :%s', $generalAlias, $paramName),
                sprintf('%s.id = :%s', $specialiseAlias, $paramName),
            ))
            ->setParameter($paramName, Uuid::fromString($groupeId));
    }
}
