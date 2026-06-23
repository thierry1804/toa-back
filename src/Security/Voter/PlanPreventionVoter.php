<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Domain\Menu\Service\PermissionChecker;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Enum\StatutPlanPrevention;
use App\Domain\User\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class PlanPreventionVoter extends Voter
{
    public const VIEW   = 'PLAN_PREVENTION_VIEW';
    public const CREATE = 'PLAN_PREVENTION_CREATE';
    public const EDIT   = 'PLAN_PREVENTION_EDIT';
    public const SUBMIT = 'PLAN_PREVENTION_SUBMIT';

    private const MENU_ROUTE = '/plans-prevention';
    private const ACTION_MAP = [
        self::VIEW   => 'VIEW',
        self::CREATE => 'CREATE',
        self::EDIT   => 'EDIT',
    ];

    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::VIEW, self::CREATE, self::EDIT, self::SUBMIT], true)) {
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

        if ($attribute === self::SUBMIT) {
            return $this->voteOnSubmit($subject, $roles, $user);
        }

        if (!$this->permissionChecker->isGranted($roles, self::MENU_ROUTE, self::ACTION_MAP[$attribute])) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        if ($attribute === self::VIEW && $subject instanceof PlanPrevention) {
            $this->checkOwnershipForPrestataire($subject, $roles, $user);
        }

        if ($attribute === self::EDIT && $subject instanceof PlanPrevention) {
            $this->checkBrouillonStatut($subject);
        }

        return true;
    }

    private function voteOnSubmit(mixed $subject, array $roles, User $user): bool
    {
        if (!in_array('ROLE_PRESTATAIRE', $roles, true)) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        if (!$subject instanceof PlanPrevention) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        $this->checkOwnershipForPrestataire($subject, $roles, $user);
        $this->checkBrouillonStatut($subject);

        return true;
    }

    private function checkOwnershipForPrestataire(PlanPrevention $plan, array $roles, User $user): void
    {
        if (!in_array('ROLE_PRESTATAIRE', $roles, true)) {
            return;
        }

        if (in_array('ROLE_HSE', $roles, true)
            || in_array('ROLE_ADMIN', $roles, true)
            || in_array('ROLE_SUPER_ADMIN', $roles, true)) {
            return;
        }

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
}
