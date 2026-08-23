<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Domain\Intervention\Entity\Take5Record;
use App\Domain\Menu\Service\PermissionChecker;
use App\Domain\User\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class Take5RecordVoter extends Voter
{
    public const CREATE = 'TAKE5_RECORD_CREATE';
    public const VIEW   = 'TAKE5_RECORD_VIEW';

    private const ACTION_KEY_MAP = [
        self::CREATE => 'take5_record.create',
        self::VIEW   => 'take5_record.view',
    ];

    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::CREATE, self::VIEW], true)) {
            return false;
        }

        return $subject instanceof Take5Record || $subject === null;
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

            if (!$subject instanceof Take5Record) {
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

        return false;
    }
}
