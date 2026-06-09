<?php

namespace App\Security\Voter;

use App\Domain\Menu\Entity\Menu;
use App\Domain\User\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class MenuVoter extends Voter
{
    public const VIEW = 'MENU_VIEW';
    public const CREATE = 'MENU_CREATE';
    public const EDIT = 'MENU_EDIT';
    public const DELETE = 'MENU_DELETE';

    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::CREATE, self::EDIT, self::DELETE])
            && ($subject instanceof Menu || $subject === null);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        return $this->security->isGranted('ROLE_SUPER_ADMIN');
    }
}
