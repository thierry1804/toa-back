<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Domain\Intervention\Entity\SuiviJournalier;
use App\Domain\Intervention\Entity\SuiviJournalierDocument;
use App\Domain\Menu\Service\PermissionChecker;
use App\Domain\User\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class SuiviJournalierVoter extends Voter
{
    public const CREATE     = 'SUIVI_JOURNALIER_CREATE';
    public const VIEW       = 'SUIVI_JOURNALIER_VIEW';
    public const EDIT       = 'SUIVI_JOURNALIER_EDIT';
    public const DELETE_DOC = 'SUIVI_JOURNALIER_DELETE_DOC';

    private const ACTION_KEY_MAP = [
        self::CREATE     => 'suivi_journalier.create',
        self::VIEW       => 'suivi_journalier.view',
        self::EDIT       => 'suivi_journalier.edit',
        self::DELETE_DOC => 'suivi_journalier.delete_document',
    ];

    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::CREATE, self::VIEW, self::EDIT, self::DELETE_DOC], true)) {
            return false;
        }

        if ($attribute === self::DELETE_DOC) {
            return $subject instanceof SuiviJournalierDocument || $subject === null;
        }

        return $subject instanceof SuiviJournalier || $subject === null;
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

            if (!$subject instanceof SuiviJournalier) {
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

            if (!$subject instanceof SuiviJournalier) {
                throw new AccessDeniedException('error.voter.access_denied');
            }

            if (!empty($canBypass)) {
                return true;
            }

            // Modifier reste strictement réservé au créateur de CE suivi
            // (contrairement à VIEW, pas d'élargissement à l'équipe/entreprise).
            $uid = $user->getUserIdentifier();
            if ($subject->getCreatedBy()?->getUserIdentifier() !== $uid) {
                throw new AccessDeniedException('error.voter.access_denied');
            }

            $suiviDate = $subject->getDate();
            $today = new \DateTimeImmutable('today');
            if ($suiviDate !== null && $suiviDate->format('Y-m-d') !== $today->format('Y-m-d')) {
                throw new AccessDeniedException('suivi_journalier.modification_interdite_apres_j');
            }

            return true;
        }

        if ($attribute === self::DELETE_DOC) {
            if (!$subject instanceof SuiviJournalierDocument) {
                throw new AccessDeniedException('error.voter.access_denied');
            }

            if (!empty($canBypass)) {
                return true;
            }

            $uid = $user->getUserIdentifier();
            $suiviCreator = $subject->getSuiviJournalier()?->getCreatedBy()?->getUserIdentifier();
            if ($suiviCreator !== $uid) {
                throw new AccessDeniedException('error.voter.access_denied');
            }

            return true;
        }

        return false;
    }

    /**
     * Vrai si l'utilisateur est le créateur du suivi ou de l'intervention liée,
     * ou s'il appartient à la même entreprise que l'un des deux (équipe).
     */
    private function hasOwnership(SuiviJournalier $suivi, User $user): bool
    {
        $uid                    = $user->getUserIdentifier();
        $isCreator              = $suivi->getCreatedBy()?->getUserIdentifier() === $uid;
        $isInterventionCreator  = $suivi->getIntervention()?->getCreatedBy()?->getUserIdentifier() === $uid;

        if ($isCreator || $isInterventionCreator) {
            return true;
        }

        $entreprise = $user->getEntreprise();
        if ($entreprise === null) {
            return false;
        }

        $creatorEntreprise             = $suivi->getCreatedBy()?->getEntreprise();
        $interventionCreatorEntreprise = $suivi->getIntervention()?->getCreatedBy()?->getEntreprise();

        return $creatorEntreprise?->getId() === $entreprise->getId()
            || $interventionCreatorEntreprise?->getId() === $entreprise->getId();
    }
}
