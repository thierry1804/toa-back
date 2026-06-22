<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Domain\Menu\Service\PermissionChecker;
use App\Domain\Permit\Entity\Permit;
use App\Domain\User\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class PermitVoter extends Voter
{
    public const VIEW = 'PERMIT_VIEW';
    public const EDIT = 'PERMIT_EDIT';
    public const DELETE = 'PERMIT_DELETE';

    private const MENU_ROUTE = '/permits';
    private const ACTION_MAP = [
        self::VIEW   => 'VIEW',
        self::EDIT   => 'EDIT',
        self::DELETE => 'DELETE',
    ];

    public function __construct(private PermissionChecker $permissionChecker) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return isset(self::ACTION_MAP[$attribute])
            && $subject instanceof Permit;
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

        // PRESTATAIRE VIEW is additionally restricted to their own permits
        if ($attribute === self::VIEW && in_array('ROLE_PRESTATAIRE', $roles, true)) {
            /** @var Permit $permit */
            $permit = $subject;
            if ($permit->getCreerPar() !== $user->getUserIdentifier()) {
                throw new AccessDeniedException('error.voter.access_denied');
            }
        }

        return true;
    }
}
