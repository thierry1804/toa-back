<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Domain\Intervention\Entity\ControleJournalier;
use App\Domain\Menu\Service\PermissionChecker;
use App\Domain\User\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ControleJournalierVoter extends Voter
{
    public const CREATE = 'CONTROLE_JOURNALIER_CREATE';
    public const VIEW   = 'CONTROLE_JOURNALIER_VIEW';
    public const EDIT   = 'CONTROLE_JOURNALIER_EDIT';

    private const ACTION_KEY_MAP = [
        self::CREATE => 'controle_journalier.create',
        self::VIEW   => 'controle_journalier.view',
        self::EDIT   => 'controle_journalier.edit',
    ];

    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::CREATE, self::VIEW, self::EDIT], true)) {
            return false;
        }

        return $subject instanceof ControleJournalier || $subject === null;
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
            return true;
        }

        if ($attribute === self::VIEW) {
            if ($subject === null) {
                return true;
            }

            if (!$subject instanceof ControleJournalier) {
                throw new AccessDeniedException('error.voter.access_denied');
            }

            if (!empty($canBypass)) {
                return true;
            }

            $uid = $user->getUserIdentifier();
            $isCreator = $subject->getCreatedBy()?->getUserIdentifier() === $uid;
            $isInterventionCreator = $subject->getIntervention()?->getCreatedBy()?->getUserIdentifier() === $uid;

            if (!$isCreator && !$isInterventionCreator) {
                throw new AccessDeniedException('error.voter.access_denied');
            }

            return true;
        }

        if ($attribute === self::EDIT) {
            if ($subject === null) {
                return true;
            }

            if (!$subject instanceof ControleJournalier) {
                throw new AccessDeniedException('error.voter.access_denied');
            }

            if (!empty($canBypass)) {
                return true;
            }

            $uid = $user->getUserIdentifier();
            if ($subject->getCreatedBy()?->getUserIdentifier() !== $uid) {
                throw new AccessDeniedException('error.voter.access_denied');
            }

            $controleDate = $subject->getDate();
            $today = new \DateTimeImmutable('today');
            if ($controleDate !== null && $controleDate->format('Y-m-d') !== $today->format('Y-m-d')) {
                throw new AccessDeniedException('controle_journalier.modification_interdite_apres_j');
            }

            return true;
        }

        return false;
    }
}
