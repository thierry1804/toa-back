<?php

namespace App\Security\Voter;

use App\Domain\ActivityPlanning\Entity\ActivityPlanning;
use App\Domain\User\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ActivityPlanningVoter extends Voter
{
    public const VIEW = 'ACTIVITY_PLANNING_VIEW';
    public const CREATE = 'ACTIVITY_PLANNING_CREATE';
    public const EDIT = 'ACTIVITY_PLANNING_EDIT';
    public const DELETE = 'ACTIVITY_PLANNING_DELETE';

    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::CREATE, self::EDIT, self::DELETE])
            && ($subject instanceof ActivityPlanning || $subject === null);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        if ($this->security->isGranted('ROLE_CHEF_PROJET')) {
            return true;
        }

        return false;
    }
}
