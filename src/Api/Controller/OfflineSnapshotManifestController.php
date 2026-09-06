<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\Offline\Service\OfflineSnapshotDataProvider;
use App\Domain\PermitTravail\Entity\PermitTravailDocument;
use App\Domain\PlanPrevention\Entity\DocumentPrevention;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Manifeste léger du snapshot hors-ligne : compte + taille estimée par module,
 * pour que le front puisse afficher un quota avant de déclencher un snapshot complet.
 * Un module non autorisé pour l'utilisateur courant est absent de la réponse
 * (jamais un tableau vide).
 */
#[Route('/api/offline/snapshot/manifest', name: 'offline_snapshot_manifest', methods: ['GET'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class OfflineSnapshotManifestController extends AbstractController
{
    /** Nombre d'entités échantillonnées par module pour estimer la taille moyenne d'un item sérialisé. */
    private const SAMPLE_SIZE = 5;

    /** Taille moyenne indicative d'un document (PDF/photo) en octets, faute de colonne `size` en base. */
    private const AVERAGE_DOCUMENT_BYTES = 375_000;

    public function __construct(
        private readonly OfflineSnapshotDataProvider $dataProvider,
        private readonly SerializerInterface $serializer,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {}

    public function __invoke(): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($this->security->isGranted('ROLE_SUPER_ADMIN')) {
            throw $this->createAccessDeniedException('offline_snapshot.super_admin_not_supported');
        }

        $modules = [];

        foreach ($this->dataProvider->accessibleEntityModules() as $module) {
            $modules[$module] = $this->estimateEntityModule($module);
        }

        $modules['sites']       = $this->estimateSites();
        $modules['referentiel'] = $this->estimateReferentiel();

        $response = new JsonResponse([
            'generatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'userId'      => $user->getId(),
            'modules'     => $modules,
            'documents'   => $this->estimateDocuments(),
        ]);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    /** @return array{count:int,estimatedBytes:int} */
    private function estimateEntityModule(string $module): array
    {
        $queryBuilder = $this->dataProvider->scopedQueryBuilder($module, null);
        $count        = (int) (clone $queryBuilder)->select('COUNT(e.id)')->getQuery()->getSingleScalarResult();

        if ($count === 0) {
            return ['count' => 0, 'estimatedBytes' => 0];
        }

        $sample = (clone $queryBuilder)->setMaxResults(self::SAMPLE_SIZE)->getQuery()->getResult();
        $avg    = $this->averageSerializedBytes($sample, $this->dataProvider->normalizationGroup($module));

        return ['count' => $count, 'estimatedBytes' => (int) round($avg * $count)];
    }

    /** @return array{count:int,estimatedBytes:int} */
    private function estimateSites(): array
    {
        $queryBuilder = $this->dataProvider->sitesQueryBuilder(null);
        $count        = (int) (clone $queryBuilder)->select('COUNT(e.id)')->getQuery()->getSingleScalarResult();

        if ($count === 0) {
            return ['count' => 0, 'estimatedBytes' => 0];
        }

        $sample = (clone $queryBuilder)->select('e')->setMaxResults(self::SAMPLE_SIZE)->getQuery()->getResult();
        $avg    = $this->averageSerializedBytes($sample, 'site:read');

        return ['count' => $count, 'estimatedBytes' => (int) round($avg * $count)];
    }

    /** @return array{count:int,estimatedBytes:int} */
    private function estimateReferentiel(): array
    {
        $categories    = $this->dataProvider->categoriesRisque();
        $installations = $this->dataProvider->installationsEquipements();
        $geoJson       = $this->dataProvider->sitesGeoJson();
        $count         = count($categories) + count($installations) + count($geoJson['features']);
        $bytes         = strlen((string) json_encode($categories))
            + strlen((string) json_encode($installations))
            + strlen((string) json_encode($geoJson));

        return ['count' => $count, 'estimatedBytes' => $bytes];
    }

    /** @return array{count:int,estimatedBytes:int} */
    private function estimateDocuments(): array
    {
        $count = 0;

        if ($this->dataProvider->isEntityModuleAccessible('plansPrevention')) {
            $planIds = $this->scopedIds('plansPrevention');
            if ($planIds !== []) {
                $count += (int) $this->entityManager->createQueryBuilder()
                    ->select('COUNT(d.id)')
                    ->from(DocumentPrevention::class, 'd')
                    ->where('d.planPrevention IN (:ids)')
                    ->setParameter('ids', $planIds)
                    ->getQuery()
                    ->getSingleScalarResult();
            }
        }

        if ($this->dataProvider->isEntityModuleAccessible('permitsTravail')) {
            $permitIds = $this->scopedIds('permitsTravail');
            if ($permitIds !== []) {
                $count += (int) $this->entityManager->createQueryBuilder()
                    ->select('COUNT(d.id)')
                    ->from(PermitTravailDocument::class, 'd')
                    ->where('d.permitTravail IN (:ids)')
                    ->setParameter('ids', $permitIds)
                    ->getQuery()
                    ->getSingleScalarResult();
            }
        }

        return ['count' => $count, 'estimatedBytes' => $count * self::AVERAGE_DOCUMENT_BYTES];
    }

    /** @return list<string> */
    private function scopedIds(string $module): array
    {
        $rows = (clone $this->dataProvider->scopedQueryBuilder($module, null))
            ->select('e.id')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): string => (string) $row['id'], $rows);
    }

    /** @param list<object> $sample */
    private function averageSerializedBytes(array $sample, string $group): float
    {
        if ($sample === []) {
            return 0.0;
        }

        $totalBytes = 0;
        foreach ($sample as $item) {
            $json = $this->serializer->serialize($item, 'json', ['groups' => [$group]]);
            $totalBytes += strlen($json);
        }

        return $totalBytes / count($sample);
    }
}
