<?php

declare(strict_types=1);

namespace App\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Domain\Intervention\Entity\SuiviJournalier;
use App\Domain\User\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * R-32 : un chef de projet TOA rattaché à des processus ne voit les suivis journaliers que
 * des dossiers de ses processus (aucune visibilité sur les autres processus, D13).
 */
class SuiviJournalierProcessusExtension implements QueryCollectionExtensionInterface
{
    public function __construct(private readonly Security $security) {}

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        Operation $operation = null,
        array $context = [],
    ): void {
        if ($resourceClass !== SuiviJournalier::class) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || $user->getProcessus() === []) {
            return;
        }

        $roles = $user->getRoles();
        if (
            !in_array('ROLE_CHEF_PROJET', $roles, true)
            || in_array('ROLE_HSE', $roles, true)
            || in_array('ROLE_ADMIN', $roles, true)
            || in_array('ROLE_SUPER_ADMIN', $roles, true)
        ) {
            return;
        }

        $root = $queryBuilder->getRootAliases()[0];
        $queryBuilder
            ->join(sprintf('%s.intervention', $root), 'sj_cp_intervention')
            ->join('sj_cp_intervention.permitTravail', 'sj_cp_permit')
            ->join('sj_cp_permit.planPrevention', 'sj_cp_plan')
            ->andWhere('sj_cp_plan.typeIntervention IN (:sj_cp_processus)')
            ->setParameter('sj_cp_processus', $user->getProcessus());
    }
}
