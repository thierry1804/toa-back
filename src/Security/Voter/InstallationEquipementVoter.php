<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Domain\Menu\Service\PermissionChecker;
use App\Domain\User\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class InstallationEquipementVoter extends Voter
{
    public const VIEW   = 'INSTALLATION_EQUIPEMENT_VIEW';
    public const CREATE = 'INSTALLATION_EQUIPEMENT_CREATE';
    public const EDIT   = 'INSTALLATION_EQUIPEMENT_EDIT';
    public const DELETE = 'INSTALLATION_EQUIPEMENT_DELETE';

    private const MENU_ROUTE = '/referentiel/installations';
    private const ACTION_MAP = [
        self::VIEW   => 'VIEW',
        self::CREATE => 'CREATE',
        self::EDIT   => 'EDIT',
        self::DELETE => 'DELETE',
    ];

    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::CREATE, self::EDIT, self::DELETE], true);
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
