<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Domain\Menu\Service\PermissionChecker;
use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Enum\StatutPermitTravail;
use App\Domain\User\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * All operations resolved via ActionKey/RoleAction records (permit_travail.*)
 * using PermissionChecker — no hardcoded ROLE_* constants.
 * The spec references RoleMenuCacheService which does not exist in this codebase;
 * PermissionChecker->hasAction() / getRoleActions() is the actual equivalent.
 */
class PermitTravailVoter extends Voter
{
    public const VIEW         = 'PERMIT_TRAVAIL_VIEW';
    public const CREATE       = 'PERMIT_TRAVAIL_CREATE';
    public const EDIT         = 'PERMIT_TRAVAIL_EDIT';
    public const SUBMIT       = 'PERMIT_TRAVAIL_SUBMIT';
    public const VALIDER_HSE  = 'PERMIT_TRAVAIL_VALIDER_HSE';
    public const REFUSER_HSE  = 'PERMIT_TRAVAIL_REFUSER_HSE';
    public const GENERATE_PDF = 'PERMIT_TRAVAIL_GENERATE_PDF';
    public const RESOUMETTRE  = 'PERMIT_TRAVAIL_RESOUMETTRE';
    public const SUIVI        = 'PERMIT_TRAVAIL_SUIVI';
    public const LOGS         = 'PERMIT_TRAVAIL_LOGS';
    public const CLOTURER     = 'PERMIT_TRAVAIL_CLOTURER';

    private const ACTION_KEY_MAP = [
        self::VIEW         => 'permit_travail.view',
        self::CREATE       => 'permit_travail.create',
        self::EDIT         => 'permit_travail.edit',
        self::SUBMIT       => 'permit_travail.submit',
        self::VALIDER_HSE  => 'permit_travail.valider_hse',
        self::REFUSER_HSE  => 'permit_travail.refuser_hse',
        self::GENERATE_PDF => 'permit_travail.generate_pdf',
        self::RESOUMETTRE  => 'permit_travail.resoumettre',
        self::SUIVI        => 'permit_travail.suivi',
        self::LOGS         => 'permit_travail.logs',
        self::CLOTURER     => 'permit_travail.cloturer',
    ];

    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::VIEW, self::CREATE, self::EDIT, self::SUBMIT, self::VALIDER_HSE, self::REFUSER_HSE, self::GENERATE_PDF, self::RESOUMETTRE, self::SUIVI, self::LOGS, self::CLOTURER], true)) {
            return false;
        }

        return $subject instanceof PermitTravail || $subject === null;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $roles      = $user->getRoles();
        $actionKey  = self::ACTION_KEY_MAP[$attribute];
        $roleActions = $this->permissionChecker->getRoleActions($roles, $actionKey);

        if (empty($roleActions)) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        $canBypass = array_filter($roleActions, fn($ra) => $ra->isBypassOwnership());

        if ($attribute === self::CREATE) {
            return true;
        }

        if ($attribute === self::VIEW && $subject === null) {
            // GetCollection: at least one matching role_action exists → grant list access
            return true;
        }

        if ($attribute === self::VIEW && $subject instanceof PermitTravail) {
            if (empty($canBypass)) {
                $this->checkOwnershipForCreatedBy($subject, $user);
            }

            return true;
        }

        if ($attribute === self::EDIT) {
            // null subject = collection-level edit check (e.g. document upload POST with read: false)
            // ownership + statut checks are delegated to the processor in that case
            if ($subject === null) {
                return true;
            }

            if (!$subject instanceof PermitTravail) {
                throw new AccessDeniedException('error.voter.access_denied');
            }

            $this->checkBrouillonStatut($subject);

            if (empty($canBypass)) {
                $this->checkOwnershipForCreatedBy($subject, $user);
            }

            return true;
        }

        if ($attribute === self::SUBMIT) {
            if (!$subject instanceof PermitTravail) {
                throw new AccessDeniedException('error.voter.access_denied');
            }

            $this->checkBrouillonStatut($subject);
            $this->checkEngagementAccepte($subject);

            if (empty($canBypass)) {
                $this->checkOwnershipForCreatedBy($subject, $user);
            }

            return true;
        }

        if ($attribute === self::VALIDER_HSE || $attribute === self::REFUSER_HSE) {
            if (!$subject instanceof PermitTravail) {
                throw new AccessDeniedException('error.voter.access_denied');
            }

            $this->checkSoumisStatut($subject);

            if (empty($canBypass)) {
                $this->checkOwnershipForCreatedBy($subject, $user);
            }

            return true;
        }

        if ($attribute === self::GENERATE_PDF) {
            if (!$subject instanceof PermitTravail) {
                throw new AccessDeniedException('error.voter.access_denied');
            }

            return true;
        }

        if ($attribute === self::RESOUMETTRE) {
            if (!$subject instanceof PermitTravail) {
                throw new AccessDeniedException('error.voter.access_denied');
            }

            $this->checkBrouillonStatut($subject);

            if (empty($canBypass)) {
                $this->checkOwnershipForCreatedBy($subject, $user);
            }

            return true;
        }

        if ($attribute === self::SUIVI || $attribute === self::LOGS) {
            // No ownership check — role_action existence is sufficient
            return true;
        }

        if ($attribute === self::CLOTURER) {
            if (!$subject instanceof PermitTravail) {
                throw new AccessDeniedException('error.voter.access_denied');
            }

            if (empty($canBypass)) {
                $this->checkOwnershipForCreatedBy($subject, $user);
            }

            return true;
        }

        return false;
    }

    private function checkOwnershipForCreatedBy(PermitTravail $permit, User $user): void
    {
        if ($permit->getCreatedBy()?->getUserIdentifier() !== $user->getUserIdentifier()) {
            throw new AccessDeniedException('error.voter.access_denied');
        }
    }

    private function checkBrouillonStatut(PermitTravail $permit): void
    {
        if ($permit->getStatut() !== StatutPermitTravail::BROUILLON) {
            throw new AccessDeniedException('permit_travail.statut_not_brouillon');
        }
    }

    private function checkEngagementAccepte(PermitTravail $permit): void
    {
        if (!$permit->isEngagementAccepte()) {
            throw new AccessDeniedException('permit_travail.engagement_obligatoire');
        }
    }

    private function checkSoumisStatut(PermitTravail $permit): void
    {
        if ($permit->getStatut() !== StatutPermitTravail::SOUMIS) {
            throw new AccessDeniedException('permit_travail.statut_not_soumis');
        }
    }
}
