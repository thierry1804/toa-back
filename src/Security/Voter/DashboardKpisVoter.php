<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Domain\Menu\Service\PermissionChecker;
use App\Domain\User\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class DashboardKpisVoter extends Voter
{
    public const VIEW = 'DASHBOARD_KPIS_VIEW';

    private const ACTION_KEY = 'dashboard.kpis.view';

    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::VIEW;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $roleActions = $this->permissionChecker->getRoleActions($user->getRoles(), self::ACTION_KEY);

        if (empty($roleActions)) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        return true;
    }
}
