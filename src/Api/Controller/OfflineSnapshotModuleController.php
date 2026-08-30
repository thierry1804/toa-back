<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\Offline\Service\OfflineSnapshotDataProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Fallback paginé du snapshot hors-ligne, utilisé par le front quand le snapshot
 * complet (`GET /api/offline/snapshot`) dépasse le seuil de taille (413). Toujours
 * filtré au périmètre de l'utilisateur courant, comme le snapshot complet — voir
 * OfflineSnapshotDataProvider. Format Hydra minimal (hydra:member / hydra:totalItems
 * / hydra:view), identique en forme aux listes API Platform existantes.
 */
#[Route(
    '/api/offline/snapshot/{module}',
    name: 'offline_snapshot_module',
    requirements: ['module' => 'plans-prevention|permits-travail|interventions|activity-plannings|sites|referentiel'],
    methods: ['GET'],
)]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class OfflineSnapshotModuleController extends AbstractController
{
    private const DEFAULT_ITEMS_PER_PAGE = 100;
    private const MAX_ITEMS_PER_PAGE     = 500;

    /** @var array<string,string> slug d'URL (kebab-case) => clé de module interne */
    private const SLUG_TO_MODULE = [
        'plans-prevention'   => 'plansPrevention',
        'permits-travail'    => 'permitsTravail',
        'interventions'      => 'interventions',
        'activity-plannings' => 'activityPlannings',
        'sites'              => 'sites',
        'referentiel'        => 'referentiel',
    ];

    public function __construct(
        private readonly OfflineSnapshotDataProvider $dataProvider,
        private readonly SerializerInterface $serializer,
    ) {}

    public function __invoke(string $module, Request $request): JsonResponse
    {
        $moduleKey = self::SLUG_TO_MODULE[$module] ?? null;
        if ($moduleKey === null) {
            throw new NotFoundHttpException('offline_snapshot.unknown_module');
        }

        [$page, $itemsPerPage] = $this->parsePagination($request);
        $since = $this->parseSince($request);

        if (in_array($moduleKey, OfflineSnapshotDataProvider::ENTITY_MODULES, true)) {
            if (!$this->dataProvider->isEntityModuleAccessible($moduleKey)) {
                throw $this->createAccessDeniedException('offline_snapshot.module_not_authorized');
            }

            return $this->entityModuleResponse($module, $moduleKey, $since, $page, $itemsPerPage);
        }

        if ($moduleKey === 'sites') {
            return $this->sitesResponse($module, $since, $page, $itemsPerPage);
        }

        return $this->referentielResponse($module, $page, $itemsPerPage);
    }

    private function entityModuleResponse(string $slug, string $moduleKey, ?\DateTimeImmutable $since, int $page, int $itemsPerPage): JsonResponse
    {
        $queryBuilder = $this->dataProvider->scopedQueryBuilder($moduleKey, $since);

        $total = (int) (clone $queryBuilder)->select('COUNT(e.id)')->getQuery()->getSingleScalarResult();
        $items = (clone $queryBuilder)
            ->select('e')
            ->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage)
            ->getQuery()
            ->getResult();

        $members = json_decode(
            $this->serializer->serialize($items, 'json', ['groups' => [$this->dataProvider->normalizationGroup($moduleKey)]]),
            true,
        );

        return $this->hydraResponse($slug, $members, $total, $page, $itemsPerPage);
    }

    private function sitesResponse(string $slug, ?\DateTimeImmutable $since, int $page, int $itemsPerPage): JsonResponse
    {
        $queryBuilder = $this->dataProvider->sitesQueryBuilder($since);

        $total = (int) (clone $queryBuilder)->select('COUNT(e.id)')->getQuery()->getSingleScalarResult();
        $items = (clone $queryBuilder)
            ->select('e')
            ->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage)
            ->getQuery()
            ->getResult();

        $members = json_decode($this->serializer->serialize($items, 'json', ['groups' => ['site:read']]), true);

        return $this->hydraResponse($slug, $members, $total, $page, $itemsPerPage);
    }

    /**
     * Référentiel (catégories de risque + installations/équipements) : listes de
     * référence volontairement non paginées côté DB ailleurs dans l'API (cf.
     * CategorieRisqueAllController/InstallationEquipementAllController) — on
     * pagine ici en mémoire par cohérence avec le format demandé, mais le volume
     * réel ne justifie quasiment jamais ce fallback.
     */
    private function referentielResponse(string $slug, int $page, int $itemsPerPage): JsonResponse
    {
        $all = [
            ...array_map(static fn (array $c): array => $c + ['type' => 'categorieRisque'], $this->dataProvider->categoriesRisque()),
            ...array_map(static fn (array $i): array => $i + ['type' => 'installationEquipement'], $this->dataProvider->installationsEquipements()),
        ];

        $total   = count($all);
        $members = array_slice($all, ($page - 1) * $itemsPerPage, $itemsPerPage);

        return $this->hydraResponse($slug, $members, $total, $page, $itemsPerPage);
    }

    /** @param list<array<string,mixed>> $members */
    private function hydraResponse(string $slug, array $members, int $totalItems, int $page, int $itemsPerPage): JsonResponse
    {
        $iri      = '/api/offline/snapshot/' . $slug;
        $lastPage = max(1, (int) ceil($totalItems / $itemsPerPage));

        $view = [
            '@id'         => sprintf('%s?page=%d&itemsPerPage=%d', $iri, $page, $itemsPerPage),
            '@type'       => 'hydra:PartialCollectionView',
            'hydra:first' => sprintf('%s?page=1&itemsPerPage=%d', $iri, $itemsPerPage),
            'hydra:last'  => sprintf('%s?page=%d&itemsPerPage=%d', $iri, $lastPage, $itemsPerPage),
        ];
        if ($page > 1) {
            $view['hydra:previous'] = sprintf('%s?page=%d&itemsPerPage=%d', $iri, $page - 1, $itemsPerPage);
        }
        if ($page < $lastPage) {
            $view['hydra:next'] = sprintf('%s?page=%d&itemsPerPage=%d', $iri, $page + 1, $itemsPerPage);
        }

        $response = new JsonResponse([
            '@context'          => '/api/contexts/OfflineSnapshotModule',
            '@id'                => $iri,
            '@type'              => 'hydra:Collection',
            'hydra:member'       => $members,
            'hydra:totalItems'   => $totalItems,
            'hydra:view'         => $view,
        ]);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    /** @return array{0:int,1:int} [page, itemsPerPage] */
    private function parsePagination(Request $request): array
    {
        $page         = max(1, (int) $request->query->get('page', 1));
        $itemsPerPage = min(self::MAX_ITEMS_PER_PAGE, max(1, (int) $request->query->get('itemsPerPage', self::DEFAULT_ITEMS_PER_PAGE)));

        return [$page, $itemsPerPage];
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
            throw new BadRequestHttpException('offline_snapshot.invalid_since');
        }
    }
}
