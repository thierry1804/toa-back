<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\Offline\Service\OfflineSnapshotDataProvider;
use App\Domain\PermitTravail\Entity\PermitTravailDocument;
use App\Domain\PermitTravail\Enum\StatutPermitTravail;
use App\Domain\PlanPrevention\Entity\DocumentPrevention;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Index des documents pour le mode hors-ligne : métadonnées seulement (jamais les
 * binaires — le front télécharge ensuite via les download paths existants, déjà
 * en cache Workbox NetworkFirst).
 */
#[Route('/api/offline/snapshot/documents', name: 'offline_snapshot_documents', methods: ['GET'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class OfflineSnapshotDocumentsController extends AbstractController
{
    private const ACTIVE_WINDOW_DAYS = 30;

    public function __construct(
        private readonly OfflineSnapshotDataProvider $dataProvider,
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('@default.storage')]
        private readonly FilesystemOperator $storage,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $scope = $request->query->get('scope', 'active');
        if (!in_array($scope, ['active', 'all'], true)) {
            $scope = 'active';
        }

        if ($scope === 'all' && !$this->security->isGranted('ROLE_HSE') && !$this->security->isGranted('ROLE_ADMIN') && !$this->security->isGranted('ROLE_SUPER_ADMIN')) {
            throw $this->createAccessDeniedException('offline_snapshot.documents_scope_all_forbidden');
        }

        $since = $this->parseSince($request);
        $cutoff = (new \DateTimeImmutable())->modify(sprintf('-%d days', self::ACTIVE_WINDOW_DAYS));

        $items = [];

        if ($this->dataProvider->isEntityModuleAccessible('plansPrevention')) {
            foreach ($this->planDocuments($since) as $doc) {
                if ($scope === 'active' && $doc->getUploadedAt() < $cutoff) {
                    continue;
                }
                $items[] = $this->toPlanDocumentEntry($doc);
            }
        }

        if ($this->dataProvider->isEntityModuleAccessible('permitsTravail')) {
            foreach ($this->permitDocuments($since) as $doc) {
                $permit = $doc->getPermitTravail();
                $isClosed = $permit?->getStatut() === StatutPermitTravail::CLOTURE;
                if ($scope === 'active' && $isClosed && $doc->getUploadedAt() < $cutoff) {
                    continue;
                }
                $items[] = $this->toPermitDocumentEntry($doc);
            }
        }

        $response = new JsonResponse([
            'generatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'items'       => $items,
        ]);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    /** @return list<DocumentPrevention> */
    private function planDocuments(?\DateTimeImmutable $since): array
    {
        $planIds = array_map(
            static fn (array $row): string => (string) $row['id'],
            (clone $this->dataProvider->scopedQueryBuilder('plansPrevention', null))
                ->select('e.id')
                ->getQuery()
                ->getArrayResult(),
        );

        if ($planIds === []) {
            return [];
        }

        $qb = $this->entityManager->createQueryBuilder()
            ->select('d')
            ->from(DocumentPrevention::class, 'd')
            ->where('d.planPrevention IN (:planIds)')
            ->setParameter('planIds', $planIds);

        if ($since !== null) {
            $qb->andWhere('d.uploadedAt >= :since')->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return list<PermitTravailDocument> */
    private function permitDocuments(?\DateTimeImmutable $since): array
    {
        $permitIds = array_map(
            static fn (array $row): string => (string) $row['id'],
            (clone $this->dataProvider->scopedQueryBuilder('permitsTravail', null))
                ->select('e.id')
                ->getQuery()
                ->getArrayResult(),
        );

        if ($permitIds === []) {
            return [];
        }

        $qb = $this->entityManager->createQueryBuilder()
            ->select('d')
            ->from(PermitTravailDocument::class, 'd')
            ->where('d.permitTravail IN (:permitIds)')
            ->setParameter('permitIds', $permitIds);

        if ($since !== null) {
            $qb->andWhere('d.uploadedAt >= :since')->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return array{id:string,module:string,ownerId:string,downloadPath:string,nom:string,mimeType:string,size:int,updatedAt:string} */
    private function toPlanDocumentEntry(DocumentPrevention $doc): array
    {
        $planId = (string) $doc->getPlanPrevention()?->getId();
        $filePath = $doc->getFilePath();

        return [
            'id'           => (string) $doc->getId(),
            'module'       => 'planPrevention',
            'ownerId'      => $planId,
            'downloadPath' => sprintf('/api/plans-prevention/%s/documents/%s/download', $planId, (string) $doc->getId()),
            'nom'          => basename($filePath ?? ''),
            'mimeType'     => (string) $doc->getMimeType(),
            'size'         => $this->fileSize($filePath),
            'updatedAt'    => $doc->getUploadedAt()?->format(\DateTimeInterface::ATOM) ?? '',
        ];
    }

    /** @return array{id:string,module:string,ownerId:string,downloadPath:string,nom:string,mimeType:string,size:int,updatedAt:string} */
    private function toPermitDocumentEntry(PermitTravailDocument $doc): array
    {
        $permitId = (string) $doc->getPermitTravail()?->getId();
        $filePath = $doc->getFilePath();

        return [
            'id'           => (string) $doc->getId(),
            'module'       => 'permitTravail',
            'ownerId'      => $permitId,
            'downloadPath' => sprintf('/api/permits-travail/%s/documents/%s/download', $permitId, (string) $doc->getId()),
            'nom'          => basename($filePath ?? ''),
            'mimeType'     => (string) $doc->getMimeType(),
            'size'         => $this->fileSize($filePath),
            'updatedAt'    => $doc->getUploadedAt()?->format(\DateTimeInterface::ATOM) ?? '',
        ];
    }

    private function fileSize(?string $path): int
    {
        if ($path === null) {
            return 0;
        }

        try {
            return $this->storage->fileSize($path);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function parseSince(Request $request): ?\DateTimeImmutable
    {
        $raw = $request->query->get('since');
        if ($raw === null || $raw === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($raw);
        } catch (\Exception) {
            return null;
        }
    }
}
