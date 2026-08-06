<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Domain\Menu\Service\PermissionChecker;
use App\Domain\User\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class CategorieRisqueVoter extends Voter
{
    public const VIEW   = 'CATEGORIE_RISQUE_VIEW';
    public const CREATE = 'CATEGORIE_RISQUE_CREATE';
    public const EDIT   = 'CATEGORIE_RISQUE_EDIT';
    public const DELETE = 'CATEGORIE_RISQUE_DELETE';

    private const MENU_ROUTE = '/referentiel/listes';
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

        $menuAction = self::ACTION_MAP[$attribute];

        if (!$this->permissionChecker->isGranted($user->getRoles(), self::MENU_ROUTE, $menuAction)) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        return true;
    }
}
