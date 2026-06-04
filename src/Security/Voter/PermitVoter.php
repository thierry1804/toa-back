<?php

namespace App\Security\Voter;

use App\Domain\Permit\Entity\Permit;
use App\Domain\User\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Bundle\SecurityBundle\Security;

class PermitVoter extends Voter
{
    public const VIEW = 'PERMIT_VIEW';
    public const EDIT = 'PERMIT_EDIT';
    public const DELETE = 'PERMIT_DELETE';

    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE])
            && $subject instanceof Permit;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Permit $permit */
        $permit = $subject;

        // Super Admin and HSE have full access
        if ($this->security->isGranted('ROLE_SUPER_ADMIN') || $this->security->isGranted('ROLE_HSE')) {
            return true;
        }

        switch ($attribute) {
            case self::VIEW:
                // Chef de projet and Collaborateur can view everything
                if ($this->security->isGranted('ROLE_CHEF_PROJET') || $this->security->isGranted('ROLE_COLLABORATEUR')) {
                    return true;
                }
                // Prestataire can view their own requests
                if ($this->security->isGranted('ROLE_PRESTATAIRE')) {
                    return $permit->getCreerPar() === $user->getUserIdentifier();
                }
                break;

            case self::EDIT:
                // Chef de projet can edit (specifically for validation and planning)
                if ($this->security->isGranted('ROLE_CHEF_PROJET')) {
                    return true;
                }
                break;

            case self::DELETE:
                // Only Super Admin and HSE can delete (already handled above)
                break;
        }

        return false;
    }
}
