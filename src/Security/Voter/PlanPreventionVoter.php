<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Domain\Menu\Service\PermissionChecker;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Enum\DecisionHse;
use App\Domain\PlanPrevention\Enum\StatutPlanPrevention;
use App\Domain\User\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class PlanPreventionVoter extends Voter
{
    public const VIEW        = 'PLAN_PREVENTION_VIEW';
    public const CREATE      = 'PLAN_PREVENTION_CREATE';
    public const EDIT        = 'PLAN_PREVENTION_EDIT';
    public const SUBMIT      = 'PLAN_PREVENTION_SUBMIT';
    public const EXAMINE     = 'PLAN_PREVENTION_EXAMINE';
    public const VALIDER_HSE = 'PLAN_PREVENTION_VALIDER_HSE';
    public const REFUSER_HSE = 'PLAN_PREVENTION_REFUSER_HSE';
    public const RESOUMETTRE  = 'PLAN_PREVENTION_RESOUMETTRE';
    public const IMPORT_KMZ   = 'PLAN_PREVENTION_IMPORT_KMZ';
    public const GENERATE_PDF = 'PLAN_PREVENTION_GENERATE_PDF';

    private const MENU_ROUTE = '/prevention';
    private const ACTION_MAP = [
        self::VIEW        => 'VIEW',
        self::CREATE      => 'CREATE',
        self::EDIT        => 'EDIT',
        self::SUBMIT      => 'CREATE',
        self::RESOUMETTRE => 'CREATE',
        self::IMPORT_KMZ  => 'CREATE',
        self::EXAMINE     => 'EDIT',
        self::VALIDER_HSE => 'EDIT',
        self::REFUSER_HSE => 'EDIT',
        self::GENERATE_PDF => 'EDIT',
    ];

    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::VIEW, self::CREATE, self::EDIT, self::SUBMIT, self::EXAMINE, self::VALIDER_HSE, self::REFUSER_HSE, self::RESOUMETTRE, self::IMPORT_KMZ, self::GENERATE_PDF], true)) {
            return false;
        }

        return $subject instanceof PlanPrevention || $subject === null;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $roles      = $user->getRoles();
        $menuAction = self::ACTION_MAP[$attribute];

        // EDIT (risques, documents, plan content) is accessible to any role that can CREATE or EDIT.
        // can_create covers PRESTATAIRE acting on their own plan; ownership checks below enforce scope.
        // All other actions require the exact mapped menu permission.
        if ($attribute === self::EDIT) {
            $canCreate = $this->permissionChecker->isGranted($roles, self::MENU_ROUTE, 'CREATE');
            $canEdit   = $this->permissionChecker->isGranted($roles, self::MENU_ROUTE, 'EDIT');
            if (!$canCreate && !$canEdit) {
                throw new AccessDeniedException('error.voter.access_denied');
            }
        } elseif (!$this->permissionChecker->isGranted($roles, self::MENU_ROUTE, $menuAction)) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        // ── VIEW ──────────────────────────────────────────────────────────────
        if ($attribute === self::VIEW && $subject instanceof PlanPrevention) {
            $this->checkOwnershipForPrestataire($subject, $roles, $user);
            $this->checkOwnershipForChefProjetView($subject, $roles, $user);
        }

        // ── CREATE ────────────────────────────────────────────────────────────
        // no extra checks beyond menu_access

        // ── EDIT (plan content) ───────────────────────────────────────────────
        if ($attribute === self::EDIT && $subject instanceof PlanPrevention) {
            $this->checkBrouillonStatut($subject);
            $this->checkOwnershipForPrestataire($subject, $roles, $user);
        }

        // ── SUBMIT ────────────────────────────────────────────────────────────
        if ($attribute === self::SUBMIT) {
            if (!$subject instanceof PlanPrevention) {
                throw new AccessDeniedException('error.voter.access_denied');
            }
            $this->checkBrouillonStatut($subject);
            $this->checkOwnershipForPrestataire($subject, $roles, $user);
        }

        // ── RESOUMETTRE ───────────────────────────────────────────────────────
        if ($attribute === self::RESOUMETTRE) {
            if (!$subject instanceof PlanPrevention) {
                throw new AccessDeniedException('error.voter.access_denied');
            }
            $this->checkBrouillonStatut($subject);
            $this->checkOwnershipForPrestataire($subject, $roles, $user);

            $hasRefus = false;
            foreach ($subject->getDecisionsHse() as $decision) {
                if ($decision->getDecision() === DecisionHse::REFUSE) {
                    $hasRefus = true;
                    break;
                }
            }
            if (!$hasRefus) {
                throw new AccessDeniedException('plan_prevention.no_previous_refus');
            }
        }

        // ── IMPORT_KMZ ────────────────────────────────────────────────────────
        if ($attribute === self::IMPORT_KMZ) {
            if (!$subject instanceof PlanPrevention) {
                throw new AccessDeniedException('error.voter.access_denied');
            }
            // PRESTATAIRE can only import on their own plan; ADMIN/SUPER_ADMIN bypass
            if (!$this->permissionChecker->isGranted($roles, self::MENU_ROUTE, 'EDIT')) {
                $this->checkOwnershipForCreatedBy($subject, $user);
            }
        }

        // ── EXAMINE ───────────────────────────────────────────────────────────
        if ($attribute === self::EXAMINE) {
            if (!$subject instanceof PlanPrevention) {
                throw new AccessDeniedException('error.voter.access_denied');
            }
            $this->checkStatutForExamine($subject);
            // CHEF_PROJET can only examine plans assigned to them; ADMIN/SUPER_ADMIN bypass
            if (!$this->permissionChecker->isGranted($roles, self::MENU_ROUTE, 'DELETE')) {
                $this->checkOwnershipForChefProjet($subject, $user);
            }
        }

        // ── VALIDER_HSE / REFUSER_HSE ─────────────────────────────────────────
        if ($attribute === self::VALIDER_HSE || $attribute === self::REFUSER_HSE) {
            if (!$subject instanceof PlanPrevention) {
                throw new AccessDeniedException('error.voter.access_denied');
            }
            $this->checkStatutForHse($subject);
        }

        // ── GENERATE_PDF ──────────────────────────────────────────────────────
        if ($attribute === self::GENERATE_PDF) {
            if (!$subject instanceof PlanPrevention) {
                throw new AccessDeniedException('error.voter.access_denied');
            }
            if ($subject->getStatut() !== StatutPlanPrevention::VALIDE_HSE) {
                throw new AccessDeniedException('plan_prevention.pdf_only_for_validated');
            }
        }

        return true;
    }

    /** PRESTATAIRE (can_create, no can_edit) can only act on their own plans. */
    private function checkOwnershipForPrestataire(PlanPrevention $plan, array $roles, User $user): void
    {
        // Roles with can_edit bypass ownership (ADMIN, SUPER_ADMIN, CHEF_PROJET, HSE after seed update).
        if ($this->permissionChecker->isGranted($roles, self::MENU_ROUTE, 'EDIT')) {
            return;
        }

        if ($plan->getCreatedBy()?->getUserIdentifier() !== $user->getUserIdentifier()) {
            throw new AccessDeniedException('error.voter.access_denied');
        }
    }

    /** CHEF_PROJET (can_edit, no can_delete) can only view plans assigned to them. */
    private function checkOwnershipForChefProjetView(PlanPrevention $plan, array $roles, User $user): void
    {
        // Roles with can_delete bypass (ADMIN, SUPER_ADMIN).
        if ($this->permissionChecker->isGranted($roles, self::MENU_ROUTE, 'DELETE')) {
            return;
        }

        // Roles with can_create but NOT can_edit = PRESTATAIRE — ownership already checked above.
        if (!$this->permissionChecker->isGranted($roles, self::MENU_ROUTE, 'EDIT')) {
            return;
        }

        // CHEF_PROJET / HSE (can_edit, no can_delete): restrict to assigned plan.
        if ($plan->getChefProjet()?->getUserIdentifier() !== $user->getUserIdentifier()) {
            throw new AccessDeniedException('error.voter.access_denied');
        }
    }

    private function checkOwnershipForChefProjet(PlanPrevention $plan, User $user): void
    {
        if ($plan->getChefProjet()?->getUserIdentifier() !== $user->getUserIdentifier()) {
            throw new AccessDeniedException('error.voter.access_denied');
        }
    }

    private function checkOwnershipForCreatedBy(PlanPrevention $plan, User $user): void
    {
        if ($plan->getCreatedBy()?->getUserIdentifier() !== $user->getUserIdentifier()) {
            throw new AccessDeniedException('error.voter.access_denied');
        }
    }

    private function checkBrouillonStatut(PlanPrevention $plan): void
    {
        if ($plan->getStatut() !== StatutPlanPrevention::BROUILLON) {
            throw new AccessDeniedException('plan_prevention.statut_not_brouillon');
        }
    }

    private function checkStatutForExamine(PlanPrevention $plan): void
    {
        $statuts = [StatutPlanPrevention::SOUMIS, StatutPlanPrevention::EN_COURS_DE_VALIDATION];

        if (!in_array($plan->getStatut(), $statuts, true)) {
            throw new AccessDeniedException('plan_prevention.statut_invalide_pour_examen');
        }
    }

    private function checkStatutForHse(PlanPrevention $plan): void
    {
        if ($plan->getStatut() !== StatutPlanPrevention::EXAMINE) {
            throw new AccessDeniedException('plan_prevention.statut_invalide_pour_decision_hse');
        }
    }

}
