<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Domain\Intervention\Entity\ControleJournalier;
use App\Domain\Menu\Service\PermissionChecker;
use App\Domain\User\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ControleJournalierVoter extends Voter
{
    public const CREATE = 'CONTROLE_JOURNALIER_CREATE';
    public const VIEW   = 'CONTROLE_JOURNALIER_VIEW';
    public const EDIT   = 'CONTROLE_JOURNALIER_EDIT';

    private const ACTION_KEY_MAP = [
        self::CREATE => 'controle_journalier.create',
        self::VIEW   => 'controle_journalier.view',
        self::EDIT   => 'controle_journalier.edit',
    ];

    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::CREATE, self::VIEW, self::EDIT], true)) {
            return false;
        }

        return $subject instanceof ControleJournalier || $subject === null;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $roles       = $user->getRoles();
        $actionKey   = self::ACTION_KEY_MAP[$attribute];
        $roleActions = $this->permissionChecker->getRoleActions($roles, $actionKey);

        if (empty($roleActions)) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        $canBypass = array_filter($roleActions, fn($ra) => $ra->isBypassOwnership());

        if ($attribute === self::CREATE) {
            return true;
        }

        if ($attribute === self::VIEW) {
            if ($subject === null) {
                return true;
            }

            if (!$subject instanceof ControleJournalier) {
                throw new AccessDeniedException('error.voter.access_denied');
            }

            if (!empty($canBypass)) {
                return true;
            }

            if (!$this->hasOwnership($subject, $user)) {
                throw new AccessDeniedException('error.voter.access_denied');
            }

            return true;
        }

        if ($attribute === self::EDIT) {
            if ($subject === null) {
                return true;
            }

            if (!$subject instanceof ControleJournalier) {
                throw new AccessDeniedException('error.voter.access_denied');
            }

            if (!empty($canBypass)) {
                return true;
            }

            // Modifier reste strictement réservé au créateur de CE contrôle
            // (contrairement à VIEW, pas d'élargissement à l'équipe/entreprise).
            $uid = $user->getUserIdentifier();
            if ($subject->getCreatedBy()?->getUserIdentifier() !== $uid) {
                throw new AccessDeniedException('error.voter.access_denied');
            }

            $controleDate = $subject->getDate();
            $today = new \DateTimeImmutable('today');
            if ($controleDate !== null && $controleDate->format('Y-m-d') !== $today->format('Y-m-d')) {
                throw new AccessDeniedException('controle_journalier.modification_interdite_apres_j');
            }

            return true;
        }

        return false;
    }

    /**
     * Vrai si l'utilisateur est le créateur du contrôle ou de l'intervention liée,
     * ou s'il appartient à la même entreprise que l'un des deux (équipe).
     */
    private function hasOwnership(ControleJournalier $controle, User $user): bool
    {
        $uid                   = $user->getUserIdentifier();
        $isCreator             = $controle->getCreatedBy()?->getUserIdentifier() === $uid;
        $isInterventionCreator = $controle->getIntervention()?->getCreatedBy()?->getUserIdentifier() === $uid;

        if ($isCreator || $isInterventionCreator) {
            return true;
        }

        $entreprise = $user->getEntreprise();
        if ($entreprise === null) {
            return false;
        }

        $creatorEntreprise             = $controle->getCreatedBy()?->getEntreprise();
        $interventionCreatorEntreprise = $controle->getIntervention()?->getCreatedBy()?->getEntreprise();

        return $creatorEntreprise?->getId() === $entreprise->getId()
            || $interventionCreatorEntreprise?->getId() === $entreprise->getId();
    }
}
