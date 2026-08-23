<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Domain\Menu\Service\PermissionChecker;
use App\Domain\User\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class UserVoter extends Voter
{
    public const VIEW             = 'USER_VIEW';
    public const CREATE           = 'USER_CREATE';
    public const EDIT             = 'USER_EDIT';
    public const DELETE           = 'USER_DELETE';
    public const UPLOAD_SIGNATURE = 'USER_UPLOAD_SIGNATURE';

    private const MENU_ROUTE = '/users';
    private const ACTION_MAP = [
        self::VIEW   => 'VIEW',
        self::CREATE => 'CREATE',
        self::EDIT   => 'EDIT',
        self::DELETE => 'DELETE',
    ];

    public function __construct(private PermissionChecker $permissionChecker) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return (isset(self::ACTION_MAP[$attribute]) || $attribute === self::UPLOAD_SIGNATURE)
            && ($subject instanceof User || $subject === null);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if ($attribute === self::UPLOAD_SIGNATURE) {
            if (!$this->permissionChecker->hasAction($user->getRoles(), 'user.upload_signature')) {
                throw new AccessDeniedException('error.voter.access_denied');
            }

            return true;
        }

        if ($this->isPrestataireScoped($user)) {
            return $this->votePrestataire($attribute, $subject, $user);
        }

        if (!$this->permissionChecker->isGranted($user->getRoles(), self::MENU_ROUTE, self::ACTION_MAP[$attribute])) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        return true;
    }

    private function votePrestataire(string $attribute, mixed $subject, User $currentUser): bool
    {
        if ($subject instanceof User && $subject->getId() === $currentUser->getId()) {
            return match ($attribute) {
                self::VIEW, self::EDIT => true,
                default => $this->requireMenuPermission($attribute, $currentUser),
            };
        }

        if (!$this->requireMenuPermission($attribute, $currentUser)) {
            return false;
        }

        if ($attribute === self::CREATE) {
            if ($currentUser->getEntreprise() === null) {
                throw new AccessDeniedException('error.voter.access_denied');
            }

            return true;
        }

        if ($subject instanceof User && !$this->usersShareEntreprise($currentUser, $subject)) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        return true;
    }

    private function requireMenuPermission(string $attribute, User $currentUser): bool
    {
        if (!$this->permissionChecker->isGranted($currentUser->getRoles(), self::MENU_ROUTE, self::ACTION_MAP[$attribute])) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        return true;
    }

    private function isPrestataireScoped(User $user): bool
    {
        $roles = $user->getRoles();

        return in_array('ROLE_PRESTATAIRE', $roles, true)
            && !in_array('ROLE_ADMIN', $roles, true)
            && !in_array('ROLE_SUPER_ADMIN', $roles, true);
    }

    private function usersShareEntreprise(User $a, User $b): bool
    {
        $entA = $a->getEntreprise();
        $entB = $b->getEntreprise();

        if ($entA === null || $entB === null || $entA->getId() === null || $entB->getId() === null) {
            return false;
        }

        return $entA->getId()->equals($entB->getId());
    }
}
