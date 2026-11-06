<?php

declare(strict_types=1);

namespace App\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Domain\Entreprise\Service\AgentProjetScope;
use App\Domain\Intervention\Entity\ControleJournalier;
use App\Domain\Intervention\Entity\SuiviJournalier;
use App\Domain\Intervention\Entity\Take5Record;
use Doctrine\ORM\QueryBuilder;

/** Un agent rattaché ne liste pas les suivis, Take 5 ou contrôles d'une autre planification. */
class AgentProjetCollectionExtension implements QueryCollectionExtensionInterface
{
    public function __construct(private readonly AgentProjetScope $agentScope)
    {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        Operation $operation = null,
        array $context = [],
    ): void {
        if (!in_array($resourceClass, [SuiviJournalier::class, Take5Record::class, ControleJournalier::class], true)) {
            return;
        }

        $root = $queryBuilder->getRootAliases()[0];
        $this->agentScope->restrictByIntervention($queryBuilder, sprintf('%s.intervention', $root));
    }
}
