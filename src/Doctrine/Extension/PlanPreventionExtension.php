<?php

declare(strict_types=1);

namespace App\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Domain\Menu\Service\PermissionChecker;
use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Enum\StatutPermitTravail;
use App\Domain\PermitTravail\Enum\TypePermitTravail;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\User\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

class PlanPreventionExtension implements QueryCollectionExtensionInterface
{
    private const MENU_ROUTE = '/prevention';

    public function __construct(
        private readonly Security $security,
        private readonly PermissionChecker $permissionChecker,
    ) {}

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        Operation $operation = null,
        array $context = [],
    ): void {
        if ($resourceClass !== PlanPrevention::class) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        if ($this->security->isGranted('ROLE_HSE')
            || $this->security->isGranted('ROLE_ADMIN')
            || $this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return;
        }

        $root = $queryBuilder->getRootAliases()[0];

        $roles = $user->getRoles();

        // R-32 : un chef de projet TOA rattaché à des processus ne voit que les plans de
        // ses processus, sans visibilité sur ceux des autres processus.
        if ($user->getProcessus() !== [] && in_array('ROLE_CHEF_PROJET', $roles, true)) {
            $queryBuilder
                ->andWhere(sprintf('%s.typeIntervention IN (:pp_processus)', $root))
                ->setParameter('pp_processus', $user->getProcessus());

            return;
        }

        // Même règle que PlanPreventionVoter::checkOwnershipForChefProjetView() :
        // hors HSE/admin, un rôle disposant de l'édition ne voit que les plans
        // dont il est chef de projet.
        if ($this->permissionChecker->isGranted($roles, self::MENU_ROUTE, 'EDIT')) {
            $queryBuilder
                ->join(sprintf('%s.chefProjet', $root), 'pp_chef')
                ->andWhere('pp_chef.email = :pp_chef_email')
                ->setParameter('pp_chef_email', $user->getUserIdentifier());

            return;
        }

        // Même règle que PlanPreventionVoter::hasOwnership() pour tous les
        // autres profils, y compris AGENT_TERRAIN : créateur ou collègue de la
        // même entreprise. Auparavant, seuls les PRESTATAIRE étaient filtrés,
        // ce qui exposait toute la collection avant un refus sur le détail.
        // R-35 : le chef de projet prestataire suit le dossier de la soumission du plan
        // jusqu'à la clôture du permis général (lecture seule).
        if (in_array('ROLE_CHEF_PROJET_PRESTATAIRE', $roles, true)) {
            $queryBuilder
                ->andWhere(sprintf('%s.soumisAt IS NOT NULL', $root))
                ->andWhere(sprintf(
                    'NOT EXISTS (SELECT 1 FROM %s pp_gen WHERE pp_gen.planPrevention = %s AND pp_gen.type = :pp_general AND pp_gen.statut IN (:pp_clotures))',
                    PermitTravail::class,
                    $root,
                ))
                ->setParameter('pp_general', TypePermitTravail::GENERAL->value)
                ->setParameter('pp_clotures', [StatutPermitTravail::CLOTURE->value, StatutPermitTravail::PV_VALIDE->value]);
        }

        $queryBuilder->join(sprintf('%s.createdBy', $root), 'pp_user');

        if ($user->getEntreprise() !== null) {
            $queryBuilder
                ->andWhere('pp_user.entreprise = :pp_entreprise')
                ->setParameter('pp_entreprise', $user->getEntreprise());

            return;
        }

        $queryBuilder
            ->andWhere('pp_user.email = :pp_current_user')
            ->setParameter('pp_current_user', $user->getUserIdentifier());
    }
}
