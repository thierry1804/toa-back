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

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if (!$this->permissionChecker->isGranted($user->getRoles(), self::MENU_ROUTE, self::ACTION_MAP[$attribute])) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        return true;
    }
}
