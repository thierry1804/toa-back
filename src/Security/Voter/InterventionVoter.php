<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Domain\Intervention\Entity\Intervention;
use App\Domain\Intervention\Enum\StatutIntervention;
use App\Domain\Menu\Service\PermissionChecker;
use App\Domain\User\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class InterventionVoter extends Voter
{
    public const CREATE  = 'INTERVENTION_CREATE';
    public const VIEW    = 'INTERVENTION_VIEW';
    public const EDIT    = 'INTERVENTION_EDIT';
    public const VALIDER = 'INTERVENTION_VALIDER';

    private const ACTION_KEY_MAP = [
        self::CREATE  => 'intervention.create',
        self::VIEW    => 'intervention.view',
        self::EDIT    => 'intervention.edit',
        self::VALIDER => 'intervention.valider_evaluation',
    ];

    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::CREATE, self::VIEW, self::EDIT, self::VALIDER], true)) {
            return false;
        }

        return $subject instanceof Intervention || $subject === null;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $roles       = $user->getRoles();
        $actionKey   = self::ACTION_KEY_MAP[$attribute];
        $roleActions = $this->permissionChecker->getRoleActions($roles, $actionKey);

        if (empty($roleActions)) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        $canBypass = array_filter($roleActions, fn($ra) => $ra->isBypassOwnership());

        if ($attribute === self::CREATE) {
            if ($subject instanceof Intervention) {
                $this->checkOwnership($subject, $user, $canBypass);
            }

            return true;
        }

        if ($attribute === self::VIEW) {
            if ($subject === null) {
                return true;
            }

            if (!$subject instanceof Intervention) {
                throw new AccessDeniedException('error.voter.access_denied');
            }

            if (!empty($canBypass)) {
                return true;
            }

            $uid = $user->getUserIdentifier();
            $isCreator       = $subject->getCreatedBy()?->getUserIdentifier() === $uid;
            $isPermitCreator = $subject->getPermitTravail()?->getCreatedBy()?->getUserIdentifier() === $uid;

            if (!$isCreator && !$isPermitCreator) {
                throw new AccessDeniedException('error.voter.access_denied');
            }

            return true;
        }

        if ($attribute === self::EDIT) {
            if ($subject === null) {
                return true;
            }

            if (!$subject instanceof Intervention) {
                throw new AccessDeniedException('error.voter.access_denied');
            }

            if ($subject->getStatut() !== StatutIntervention::EN_PREPARATION) {
                throw new AccessDeniedException('intervention.statut_not_en_preparation');
            }

            if (empty($canBypass)) {
                $this->checkOwnership($subject, $user, $canBypass);
            }

            return true;
        }

        if ($attribute === self::VALIDER) {
            if (!$subject instanceof Intervention) {
                throw new AccessDeniedException('error.voter.access_denied');
            }

            if (empty($canBypass)) {
                $this->checkOwnership($subject, $user, $canBypass);
            }

            return true;
        }

        return false;
    }

    private function checkOwnership(Intervention $intervention, User $user, array $canBypass): void
    {
        if (
            empty($canBypass)
            && $intervention->getCreatedBy()?->getUserIdentifier() !== $user->getUserIdentifier()
        ) {
            throw new AccessDeniedException('error.voter.access_denied');
        }
    }
}
