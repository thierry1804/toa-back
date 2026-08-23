<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Domain\Entreprise\Entity\Entreprise;
use App\Domain\Menu\Service\PermissionChecker;
use App\Domain\User\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class EntrepriseVoter extends Voter
{
    public const VIEW   = 'ENTREPRISE_VIEW';
    public const CREATE = 'ENTREPRISE_CREATE';
    public const EDIT   = 'ENTREPRISE_EDIT';
    public const DELETE = 'ENTREPRISE_DELETE';

    private const MENU_ROUTE = '/referentiel/entreprises';
    private const ACTION_MAP = [
        self::VIEW   => 'VIEW',
        self::CREATE => 'CREATE',
        self::EDIT   => 'EDIT',
        self::DELETE => 'DELETE',
    ];

    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return isset(self::ACTION_MAP[$attribute])
            && ($subject instanceof Entreprise || $subject === null);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if ($this->isAdminGranted($user, $attribute)) {
            return true;
        }

        if (!$this->isPrestataireScoped($user)) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        return match ($attribute) {
            self::VIEW => $this->canPrestataireView($user, $subject),
            self::CREATE => $this->canPrestataireCreate($user),
            self::EDIT => $this->canPrestataireEdit($user, $subject),
            self::DELETE => throw new AccessDeniedException('error.voter.access_denied'),
            default => false,
        };
    }

    private function isAdminGranted(User $user, string $attribute): bool
    {
        return $this->permissionChecker->isGranted(
            $user->getRoles(),
            self::MENU_ROUTE,
            self::ACTION_MAP[$attribute],
        );
    }

    private function isPrestataireScoped(User $user): bool
    {
        $roles = $user->getRoles();

        return in_array('ROLE_PRESTATAIRE', $roles, true)
            && !in_array('ROLE_ADMIN', $roles, true)
            && !in_array('ROLE_SUPER_ADMIN', $roles, true);
    }

    private function canPrestataireView(User $user, mixed $subject): bool
    {
        if (!$subject instanceof Entreprise) {
            return true;
        }

        return $this->ownsEntreprise($user, $subject);
    }

    private function canPrestataireCreate(User $user): bool
    {
        if ($user->getEntreprise() !== null) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        return true;
    }

    private function canPrestataireEdit(User $user, mixed $subject): bool
    {
        if (!$subject instanceof Entreprise || !$this->ownsEntreprise($user, $subject)) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        return true;
    }

    private function ownsEntreprise(User $user, Entreprise $entreprise): bool
    {
        $owned = $user->getEntreprise();
        if ($owned === null || $owned->getId() === null || $entreprise->getId() === null) {
            return false;
        }

        return $owned->getId()->equals($entreprise->getId());
    }
}
