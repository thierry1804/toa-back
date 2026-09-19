<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\ActivityPlanning\Service\PlanificationsDisponiblesProvider;
use App\Domain\Offline\Service\OfflineSnapshotDataProvider;
use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Entity\PermitTravailPdf;
use App\Domain\PermitTravail\Entity\PvReceptionPdf;
use App\Domain\PermitTravail\Enum\StatutPermitTravailPdf;
use App\Domain\PermitTravail\Enum\StatutPvReceptionPdf;
use App\Domain\PermitTravail\Enum\TypeDocumentPermitTravail;
use App\Domain\PermitTravail\Service\PermitDocumentRequirementResolver;
use App\Domain\PlanPrevention\Entity\PlanPreventionPdf;
use App\Domain\PlanPrevention\Enum\StatutPlanPreventionPdf;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Données calculées côté serveur dont les écrans ont besoin hors-ligne mais qui ne sont pas
 * des entités du snapshot : pièces requises de chaque permis visible (R-22/R-23) et planifications
 * disponibles pour un nouveau plan (prestataire), et PDF déjà générés à précharger. Toujours renvoyé en
 * entier (petit volume).
 */
#[Route('/api/offline/snapshot/extras', name: 'offline_snapshot_extras', methods: ['GET'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class OfflineSnapshotExtrasController extends AbstractController
{
    public function __construct(
        private readonly OfflineSnapshotDataProvider $dataProvider,
        private readonly PermitDocumentRequirementResolver $requirementResolver,
        private readonly PlanificationsDisponiblesProvider $planificationsProvider,
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $values = static fn (array $types): array => array_map(
            static fn (TypeDocumentPermitTravail $type): string => $type->value,
            $types,
        );

        $permitsDocumentsRequis = [];
        $generatedPdfs = [];
        if ($this->dataProvider->isEntityModuleAccessible('permitsTravail')) {
            /** @var list<PermitTravail> $permits */
            $permits = $this->dataProvider->scopedQueryBuilder('permitsTravail', null)->getQuery()->getResult();
            foreach ($permits as $permit) {
                $permitsDocumentsRequis[] = [
                    'id' => (string) $permit->getId(),
                    'soumission' => $values($this->requirementResolver->getRequiredDocumentTypes($permit)),
                    'cloture' => $values($this->requirementResolver->getRequiredClotureDocumentTypes($permit)),
                    'apnApi' => $this->requirementResolver->isApnApiSite($permit),
                ];
            }

            $permitIds = array_map(static fn (PermitTravail $permit): string => (string) $permit->getId(), $permits);
            $generatedPdfs = array_merge(
                $generatedPdfs,
                $this->pdfEntries(PermitTravailPdf::class, 'permitTravail', StatutPermitTravailPdf::GENERE, $permitIds, '/api/permits-travail/%s/pdf/download', 'permit_travail.pdf'),
                $this->pdfEntries(PvReceptionPdf::class, 'permitTravail', StatutPvReceptionPdf::GENERE, $permitIds, '/api/permits-travail/%s/pv/download', 'pv_reception.pdf'),
            );
        }

        if ($this->dataProvider->isEntityModuleAccessible('plansPrevention')) {
            $planIds = array_map(
                static fn (array $row): string => (string) $row['id'],
                $this->dataProvider->scopedQueryBuilder('plansPrevention', null)->select('e.id')->getQuery()->getArrayResult(),
            );
            $generatedPdfs = array_merge(
                $generatedPdfs,
                $this->pdfEntries(PlanPreventionPdf::class, 'planPrevention', StatutPlanPreventionPdf::GENERE, $planIds, '/api/plans-prevention/%s/pdf/download', 'plan_prevention.pdf'),
            );
        }

        $planificationsDisponibles = $this->security->isGranted('ROLE_PRESTATAIRE')
            ? $this->planificationsProvider->forUser($user)
            : [];

        $response = new JsonResponse([
            'generatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'permitsDocumentsRequis' => $permitsDocumentsRequis,
            'planificationsDisponibles' => $planificationsDisponibles,
            'generatedPdfs' => $generatedPdfs,
        ]);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    /**
     * PDF déjà générés (statut GENERE) pour les entités visibles : le front les précharge pour les rendre
     * consultables hors-ligne. Un PDF non généré n'est pas listé (il ne peut pas être produit hors-ligne).
     *
     * @param class-string           $pdfClass
     * @param list<string>           $ownerIds
     * @param \BackedEnum           $generatedStatus
     *
     * @return list<array{downloadPath: string, nom: string}>
     */
    private function pdfEntries(string $pdfClass, string $ownerField, \BackedEnum $generatedStatus, array $ownerIds, string $pathFormat, string $filename): array
    {
        if ($ownerIds === []) {
            return [];
        }

        /** @var list<array{ownerId: mixed}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select(sprintf('IDENTITY(p.%s) AS ownerId', $ownerField))
            ->from($pdfClass, 'p')
            ->where(sprintf('p.%s IN (:ids)', $ownerField))
            ->andWhere('p.statut = :statut')
            ->setParameter('ids', $ownerIds)
            ->setParameter('statut', $generatedStatus)
            ->getQuery()
            ->getArrayResult();

        $entries = [];
        foreach ($rows as $row) {
            $ownerId = (string) $row['ownerId'];
            $entries[$ownerId] = ['downloadPath' => sprintf($pathFormat, $ownerId), 'nom' => $filename];
        }

        return array_values($entries);
    }
}
