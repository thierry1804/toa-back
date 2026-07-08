<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Domain\Menu\Service\PermissionChecker;
use App\Domain\Role\Entity\Role;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class RoleVoter extends Voter
{
    public const VIEW   = 'ROLE_VIEW';
    public const CREATE = 'ROLE_CREATE';
    public const EDIT   = 'ROLE_EDIT';
    public const DELETE = 'ROLE_DELETE';

    private const MENU_ROUTE = '/roles';
    private const ACTION_MAP = [
        self::VIEW   => 'VIEW',
        self::CREATE => 'CREATE',
        self::EDIT   => 'EDIT',
        self::DELETE => 'DELETE',
    ];

    public function __construct(
        private readonly PermissionChecker $permissionChecker,
        private readonly UserRepository $userRepository,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return isset(self::ACTION_MAP[$attribute])
            && ($subject instanceof Role || $subject === null);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $roles = $user->getRoles();

        if (!$this->permissionChecker->isGranted($roles, self::MENU_ROUTE, self::ACTION_MAP[$attribute])) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        if ($attribute === self::DELETE && $subject instanceof Role) {
            if ($subject->isSystem()) {
                throw new AccessDeniedException('role_system_protected');
            }

            if (count($this->userRepository->findByRole($subject->getName())) > 0) {
                throw new AccessDeniedException('role_in_use');
            }
        }

        return true;
    }
}
