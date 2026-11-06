<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Domain\Entreprise\Entity\SousTraitant;
use App\Domain\User\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class SousTraitantVoter extends Voter
{
    public const VIEW = 'SOUS_TRAITANT_VIEW';
    public const CREATE = 'SOUS_TRAITANT_CREATE';
    public const EDIT = 'SOUS_TRAITANT_EDIT';
    public const DELETE = 'SOUS_TRAITANT_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::CREATE, self::EDIT, self::DELETE], true)
            && ($subject instanceof SousTraitant || $subject === null);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $roles = $user->getRoles();
        if ($this->isBypass($roles)) {
            return true;
        }

        if (!$this->managesTeam($roles) && !in_array('ROLE_AGENT_TERRAIN', $roles, true)) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        if ($attribute !== self::VIEW && !$this->managesTeam($roles)) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        if (!$subject instanceof SousTraitant) {
            return $user->getEntreprise() !== null || $this->isBypass($roles);
        }

        if (in_array('ROLE_AGENT_TERRAIN', $roles, true) && !$this->managesTeam($roles)) {
            if ($user->getSousTraitant()?->getId() === null || (string) $user->getSousTraitant()->getId() !== (string) $subject->getId()) {
                throw new AccessDeniedException('error.voter.access_denied');
            }

            return true;
        }

        $entrepriseId = $user->getEntreprise()?->getId();
        if ($entrepriseId === null || (string) $subject->getEntreprise()?->getId() !== (string) $entrepriseId) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        return true;
    }

    /** @param list<string> $roles */
    private function isBypass(array $roles): bool
    {
        foreach (['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_HSE', 'ROLE_DIRECTION'] as $role) {
            if (in_array($role, $roles, true)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $roles */
    private function managesTeam(array $roles): bool
    {
        return in_array('ROLE_PRESTATAIRE', $roles, true)
            || in_array('ROLE_CHEF_PROJET_PRESTATAIRE', $roles, true);
    }
}
