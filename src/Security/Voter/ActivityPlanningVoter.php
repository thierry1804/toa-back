<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Domain\ActivityPlanning\Entity\ActivityPlanning;
use App\Domain\Menu\Service\PermissionChecker;
use App\Domain\User\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ActivityPlanningVoter extends Voter
{
    public const VIEW = 'ACTIVITY_PLANNING_VIEW';
    public const CREATE = 'ACTIVITY_PLANNING_CREATE';
    public const EDIT = 'ACTIVITY_PLANNING_EDIT';
    public const DELETE = 'ACTIVITY_PLANNING_DELETE';

    private const MENU_ROUTE = '/planning';
    private const ACTION_MAP = [
        self::VIEW   => 'VIEW',
        self::CREATE => 'CREATE',
        self::EDIT   => 'EDIT',
        self::DELETE => 'DELETE',
    ];

    public function __construct(private PermissionChecker $permissionChecker) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return isset(self::ACTION_MAP[$attribute])
            && ($subject instanceof ActivityPlanning || $subject === null);
    }

    private const BYPASS_ROLES = ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_HSE', 'ROLE_DG'];

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if (!$this->permissionChecker->isGranted($user->getRoles(), self::MENU_ROUTE, self::ACTION_MAP[$attribute])) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        if ($subject === null) {
            return true;
        }

        if ($this->hasBypass($user)) {
            return true;
        }

        if ($subject instanceof ActivityPlanning) {
            $owner = $subject->getCreatedBy();
            if ($owner !== null && $owner->getUserIdentifier() !== $user->getUserIdentifier()) {
                throw new AccessDeniedException('error.voter.access_denied');
            }
        }

        return true;
    }

    private function hasBypass(User $user): bool
    {
        foreach (self::BYPASS_ROLES as $role) {
            if (in_array($role, $user->getRoles(), true)) {
                return true;
            }
        }

        return false;
    }
}
