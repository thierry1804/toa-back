<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Domain\Menu\Service\PermissionChecker;
use App\Domain\User\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class InterventionSuiviVoter extends Voter
{
    public const VIEW_DASHBOARD = 'INTERVENTION_SUIVI_DASHBOARD';
    public const VIEW_DETAIL    = 'INTERVENTION_SUIVI_DETAIL';

    private const ACTION_KEY_MAP = [
        self::VIEW_DASHBOARD => 'intervention.suivi.dashboard',
        self::VIEW_DETAIL    => 'intervention.suivi.detail',
    ];

    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW_DASHBOARD, self::VIEW_DETAIL], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $roleActions = $this->permissionChecker->getRoleActions(
            $user->getRoles(),
            self::ACTION_KEY_MAP[$attribute],
        );

        if (empty($roleActions)) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        return true;
    }
}
