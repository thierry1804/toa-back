<?php

declare(strict_types=1);

namespace App\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Enum\TypePermitTravail;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\RequestStack;

class PermitTravailSuiviExtension implements QueryCollectionExtensionInterface
{
    public function __construct(private readonly RequestStack $requestStack) {}

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

        $request = $this->requestStack->getMainRequest();
        if ($request === null) {
            return;
        }

        $root = $queryBuilder->getRootAliases()[0];

        $codeSite = $request->query->get('codeSite');
        if ($codeSite !== null && $codeSite !== '') {
            $queryBuilder
                ->andWhere(sprintf('%s.codeSite = :suivi_code_site', $root))
                ->setParameter('suivi_code_site', $codeSite);
        }

        $typePermis = $request->query->get('typePermis');
        if ($typePermis !== null && $typePermis !== '') {
            $typeEnum = TypePermitTravail::tryFrom($typePermis);
            if ($typeEnum !== null) {
                $queryBuilder
                    ->andWhere(sprintf('%s.type = :suivi_type_permis', $root))
                    ->setParameter('suivi_type_permis', $typeEnum->value);
            }
        }
    }
}
