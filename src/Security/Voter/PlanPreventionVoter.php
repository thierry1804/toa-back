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
    public const RESOUMETTRE = 'PLAN_PREVENTION_RESOUMETTRE';
    public const IMPORT_KMZ  = 'PLAN_PREVENTION_IMPORT_KMZ';

    private const MENU_ROUTE = '/plans-prevention';
    private const ACTION_MAP = [
        self::VIEW   => 'VIEW',
        self::CREATE => 'CREATE',
        self::EDIT   => 'EDIT',
    ];

    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::VIEW, self::CREATE, self::EDIT, self::SUBMIT, self::EXAMINE, self::VALIDER_HSE, self::REFUSER_HSE, self::RESOUMETTRE, self::IMPORT_KMZ], true)) {
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

        $roles = $user->getRoles();

        if ($attribute === self::IMPORT_KMZ) {
            return $this->voteOnImportKmz($subject, $roles, $user);
        }

        if ($attribute === self::RESOUMETTRE) {
            return $this->voteOnResoumettre($subject, $roles, $user);
        }

        if ($attribute === self::SUBMIT) {
            return $this->voteOnSubmit($subject, $roles, $user);
        }

        if ($attribute === self::EXAMINE) {
            return $this->voteOnExamine($subject, $roles, $user);
        }

        if ($attribute === self::VALIDER_HSE) {
            return $this->voteOnValiderHse($subject, $roles);
        }

        if ($attribute === self::REFUSER_HSE) {
            return $this->voteOnRefuserHse($subject, $roles);
        }

        if (!$this->permissionChecker->isGranted($roles, self::MENU_ROUTE, self::ACTION_MAP[$attribute])) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        if ($attribute === self::EDIT && $this->permissionChecker->isGranted($roles, self::MENU_ROUTE, 'VIEW')
            && !$this->permissionChecker->isGranted($roles, self::MENU_ROUTE, 'EDIT')) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        if ($attribute === self::VIEW && $subject instanceof PlanPrevention) {
            $this->checkOwnershipForPrestataire($subject, $roles, $user);
            $this->checkOwnershipForRestrictedRole($subject, $roles, $user);
        }

        if ($attribute === self::EDIT && $subject instanceof PlanPrevention) {
            $this->checkBrouillonStatut($subject);
            $this->checkOwnershipForPrestataire($subject, $roles, $user);
        }

        return true;
    }

    private function voteOnImportKmz(mixed $subject, array $roles, User $user): bool
    {
        $roleActions = $this->permissionChecker->getRoleActions($roles, 'plan_prevention.import_kmz');

        if (empty($roleActions)) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        if (!$subject instanceof PlanPrevention) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        $canBypass = array_filter($roleActions, fn($ra) => $ra->isBypassOwnership());
        if (empty($canBypass)) {
            $this->checkOwnershipForChefProjet($subject, $user);
        }

        return true;
    }

    private function voteOnResoumettre(mixed $subject, array $roles, User $user): bool
    {
        $roleActions = $this->permissionChecker->getRoleActions($roles, 'plan_prevention.resoumettre');

        if (empty($roleActions)) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        if (!$subject instanceof PlanPrevention) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        $canBypass = array_filter($roleActions, fn($ra) => $ra->isBypassOwnership());
        if (empty($canBypass)) {
            $this->checkOwnershipForCreatedBy($subject, $user);
        }

        $this->checkBrouillonStatut($subject);

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

        return true;
    }

    private function voteOnSubmit(mixed $subject, array $roles, User $user): bool
    {
        $roleActions = $this->permissionChecker->getRoleActions($roles, 'plan_prevention.submit');

        if (empty($roleActions)) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        if (!$subject instanceof PlanPrevention) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        $canBypass = array_filter($roleActions, fn($ra) => $ra->isBypassOwnership());
        if (empty($canBypass)) {
            $this->checkOwnershipForCreatedBy($subject, $user);
        }

        $this->checkBrouillonStatut($subject);

        return true;
    }

    private function voteOnExamine(mixed $subject, array $roles, User $user): bool
    {
        $roleActions = $this->permissionChecker->getRoleActions($roles, 'plan_prevention.examine');

        if (empty($roleActions)) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        if (!$subject instanceof PlanPrevention) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        $canBypass = array_filter($roleActions, fn($ra) => $ra->isBypassOwnership());
        if (empty($canBypass)) {
            $this->checkOwnershipForChefProjet($subject, $user);
        }

        $this->checkStatutForExamine($subject);

        return true;
    }

    private function voteOnValiderHse(mixed $subject, array $roles): bool
    {
        $roleActions = $this->permissionChecker->getRoleActions($roles, 'plan_prevention.valider_hse');

        if (empty($roleActions)) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        if (!$subject instanceof PlanPrevention) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        $this->checkStatutForHse($subject);

        return true;
    }

    private function voteOnRefuserHse(mixed $subject, array $roles): bool
    {
        $roleActions = $this->permissionChecker->getRoleActions($roles, 'plan_prevention.refuser_hse');

        if (empty($roleActions)) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        if (!$subject instanceof PlanPrevention) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        $this->checkStatutForHse($subject);

        return true;
    }

    private function checkOwnershipForPrestataire(PlanPrevention $plan, array $roles, User $user): void
    {
        if (!$this->permissionChecker->hasAction($roles, 'plan_prevention.submit')) {
            return;
        }

        $canBypass = array_filter(
            $this->permissionChecker->getRoleActions($roles, 'plan_prevention.submit'),
            fn($ra) => $ra->isBypassOwnership()
        );

        if (!empty($canBypass)) {
            return;
        }

        if ($plan->getCreatedBy()?->getUserIdentifier() !== $user->getUserIdentifier()) {
            throw new AccessDeniedException('error.voter.access_denied');
        }
    }

    private function checkOwnershipForRestrictedRole(PlanPrevention $plan, array $roles, User $user): void
    {
        $examineActions = $this->permissionChecker->getRoleActions($roles, 'plan_prevention.examine');
        if (empty($examineActions)) {
            return;
        }

        $canBypass = array_filter($examineActions, fn($ra) => $ra->isBypassOwnership());
        if (!empty($canBypass)) {
            return;
        }

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
