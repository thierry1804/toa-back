<?php

declare(strict_types=1);

namespace App\Domain\Entreprise\Service;

use App\Domain\ActivityPlanning\Entity\ActivityPlanning;
use App\Domain\Entreprise\Entity\SousTraitant;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Un agent de terrain rattaché à un sous-traitant ne voit que les dossiers
 * des planifications affectées à ce sous-traitant. Sans sous-traitant, il
 * garde la visibilité de toute son entreprise.
 */
class AgentProjetScope
{
    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function restricts(User $user): bool
    {
        $roles = $user->getRoles();
        foreach ([
            'ROLE_PRESTATAIRE',
            'ROLE_CHEF_PROJET_PRESTATAIRE',
            'ROLE_CHEF_PROJET',
            'ROLE_HSE',
            'ROLE_DIRECTION',
            'ROLE_ADMIN',
            'ROLE_SUPER_ADMIN',
        ] as $broader) {
            if (in_array($broader, $roles, true)) {
                return false;
            }
        }

        return in_array('ROLE_AGENT_TERRAIN', $roles, true) && $user->getSousTraitant() !== null;
    }

    public function allows(User $user, ?int $planificationId): bool
    {
        if (!$this->restricts($user)) {
            return true;
        }
        if ($planificationId === null || $user->getSousTraitant() === null) {
            return false;
        }

        return $this->isAssigned($planificationId, $user->getSousTraitant());
    }

    /**
     * Mail de validation HSE. Un plan sans planification n'est envoyé à aucun
     * agent. Un agent sans sous-traitant reste destinataire dès qu'une
     * planification existe. Un agent rattaché ne l'est que si son
     * sous-traitant est affecté à cette planification.
     */
    public function includeInValidationMail(User $agent, ?int $planificationId): bool
    {
        if ($planificationId === null) {
            return false;
        }
        $sousTraitant = $agent->getSousTraitant();
        if ($sousTraitant === null) {
            return true;
        }

        return $this->isAssigned($planificationId, $sousTraitant);
    }

    /** Restreint une requête dont un champ entier porte l'id de planification. */
    public function restrictByPlanificationId(QueryBuilder $qb, string $planificationIdDql): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || !$this->restricts($user)) {
            return;
        }

        $qb->andWhere(sprintf(
            'EXISTS (SELECT 1 FROM %s agent_scope_ap JOIN agent_scope_ap.sousTraitants agent_scope_st WHERE agent_scope_ap.id = %s AND agent_scope_st = :agentSousTraitantScope)',
            ActivityPlanning::class,
            $planificationIdDql,
        ))->setParameter('agentSousTraitantScope', $user->getSousTraitant());
    }

    /** Restreint une requête dont une association pointe vers l'intervention. */
    public function restrictByIntervention(QueryBuilder $qb, string $interventionAssociationDql): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || !$this->restricts($user)) {
            return;
        }

        $qb->join($interventionAssociationDql, 'agent_scope_intervention')
            ->join('agent_scope_intervention.permitTravail', 'agent_scope_permit')
            ->join('agent_scope_permit.planPrevention', 'agent_scope_plan');
        $this->restrictByPlanificationId($qb, 'agent_scope_plan.planificationId');
    }

    /** Restreint une requête dont la racine est une planification. */
    public function restrictPlanning(QueryBuilder $qb, string $rootAlias): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || !$this->restricts($user)) {
            return;
        }

        $qb->andWhere(sprintf(
            'EXISTS (SELECT 1 FROM %s agent_scope_ap JOIN agent_scope_ap.sousTraitants agent_scope_st WHERE agent_scope_ap = %s AND agent_scope_st = :agentSousTraitantScope)',
            ActivityPlanning::class,
            $rootAlias,
        ))->setParameter('agentSousTraitantScope', $user->getSousTraitant());
    }

    public function isAssigned(int $planificationId, SousTraitant $sousTraitant): bool
    {
        $count = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(ap.id)')
            ->from(ActivityPlanning::class, 'ap')
            ->join('ap.sousTraitants', 'st')
            ->andWhere('ap.id = :id')
            ->andWhere('st = :sousTraitant')
            ->setParameter('id', $planificationId)
            ->setParameter('sousTraitant', $sousTraitant)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
