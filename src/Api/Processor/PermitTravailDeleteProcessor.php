<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Menu\Service\PermissionChecker;
use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Enum\ProcessusPermitTravail;
use App\Domain\PermitTravail\Enum\StatutPermitTravail;
use App\Domain\PermitTravail\Repository\PermitTravailGroupeRepository;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Suppression sécurisée d'un permis de travail en BROUILLON.
 *
 * L'ownership et le statut BROUILLON du permis ciblé (`$data`) sont déjà
 * validés en amont par PermitTravailVoter::DELETE (security de l'opération
 * ApiResource). Ce processor gère la partie que le Voter ne peut pas voir :
 * le compagnon d'un groupe "Nouveau site" (Général + Électrique/Hauteur),
 * qui doit être supprimé avec lui — ou bloquer la suppression si ce
 * compagnon n'est pas dans un état permettant sa propre suppression.
 */
final class PermitTravailDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PermitTravailGroupeRepository $groupeRepository,
        private readonly PermissionChecker $permissionChecker,
        private readonly TokenStorageInterface $tokenStorage,
        #[Autowire('@default.storage')]
        private readonly FilesystemOperator $storage,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof PermitTravail) {
            return null;
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        $canBypass = false;
        if ($user instanceof User) {
            $roleActions = $this->permissionChecker->getRoleActions($user->getRoles(), 'permit_travail.delete');
            $canBypass = (bool) array_filter($roleActions, static fn($ra) => $ra->isBypassOwnership());
        }

        $toDelete = [$data];

        if ($data->getProcessus() === ProcessusPermitTravail::NOUVEAU_SITE) {
            $groupe = $this->groupeRepository->findByCodeSiteAndPlan($data->getCodeSite(), $data->getPlanPrevention());

            if ($groupe !== null) {
                $companion = $groupe->getPermitGeneral()?->getId() === $data->getId()
                    ? $groupe->getPermitSpecialise()
                    : $groupe->getPermitGeneral();

                if ($companion !== null) {
                    if ($companion->getStatut() !== StatutPermitTravail::BROUILLON) {
                        throw new ConflictHttpException('permit_travail.groupe_delete_conflict_statut_not_brouillon');
                    }

                    if (!$canBypass && $companion->getCreatedBy()?->getUserIdentifier() !== $user?->getUserIdentifier()) {
                        throw new AccessDeniedException('permit_travail.groupe_delete_conflict_owner');
                    }

                    $toDelete[] = $companion;
                }
            }
        }

        // Sécurité supplémentaire : ne jamais supprimer un permis déjà rattaché à une
        // intervention (ne devrait jamais arriver pour un BROUILLON, mais la FK est en
        // CASCADE côté base — mieux vaut bloquer explicitement qu'entraîner une suppression
        // silencieuse d'intervention).
        foreach ($toDelete as $permit) {
            if ($permit->getInterventionId() !== null) {
                throw new ConflictHttpException('permit_travail.delete_conflict_intervention_liee');
            }
        }

        $filePaths = [];
        foreach ($toDelete as $permit) {
            foreach ($permit->getDocuments() as $document) {
                $filePaths[] = $document->getFilePath();
            }
        }

        $this->entityManager->wrapInTransaction(function () use ($toDelete): void {
            foreach ($toDelete as $permit) {
                // documents/decisionsHse/versions sont supprimés en cascade (ORM) ; le
                // PermitTravailGroupe éventuel l'est en cascade (FK ON DELETE CASCADE/SET NULL).
                $this->entityManager->remove($permit);
            }
            $this->entityManager->flush();
        });

        // Nettoyage des fichiers physiques seulement après le succès de la transaction,
        // pour ne jamais perdre un fichier référencé par une ligne encore en base.
        foreach ($filePaths as $filePath) {
            if ($filePath === null) {
                continue;
            }

            try {
                $this->storage->delete($filePath);
            } catch (\Throwable) {
                // Fichier déjà absent du storage : sans impact sur la suppression logique.
            }
        }

        return null;
    }
}
