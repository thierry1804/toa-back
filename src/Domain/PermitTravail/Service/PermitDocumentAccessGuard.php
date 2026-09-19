<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Service;

use App\Domain\Menu\Service\PermissionChecker;
use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Enum\StatutPermitTravail;
use App\Domain\PermitTravail\Enum\TypeDocumentPermitTravail;
use App\Domain\User\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Règles communes de modification des documents d'un permis : dépôt, suppression,
 * marquage « non applicable ».
 */
class PermitDocumentAccessGuard
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly PermissionChecker $permissionChecker,
    ) {
    }

    public function assertCanModify(PermitTravail $permit, TypeDocumentPermitTravail $type): void
    {
        $isClotureUpload = $type->isClotureType()
            && in_array($permit->getStatut(), [StatutPermitTravail::VALIDE_HSE, StatutPermitTravail::EN_COURS], true);

        if ($permit->getStatut() !== StatutPermitTravail::BROUILLON && !$isClotureUpload) {
            throw new AccessDeniedException('permit_travail.statut_not_brouillon');
        }

        $currentUser = $this->tokenStorage->getToken()?->getUser();
        if ($currentUser instanceof User) {
            $roleActions = $this->permissionChecker->getRoleActions($currentUser->getRoles(), 'permit_travail.edit');
            $canBypass = array_filter($roleActions, static fn ($ra) => $ra->isBypassOwnership());
            if (empty($canBypass)) {
                $permitOwner = $permit->getCreatedBy();
                if ($permitOwner !== null && $permitOwner->getUserIdentifier() !== $currentUser->getUserIdentifier()) {
                    throw new AccessDeniedException('error.voter.access_denied');
                }
            }
        }
    }
}
