<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\Offline\Service\OfflineSnapshotDataProvider;
use App\Domain\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Snapshot métier hors-ligne : mêmes rôles / mêmes permissions / même isolation
 * prestataire-entreprise que les GET existants (voir OfflineSnapshotDataProvider).
 * Jamais un miroir de toute la base — un module non autorisé est absent de la réponse.
 */
#[Route('/api/offline/snapshot', name: 'offline_snapshot', methods: ['GET'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class OfflineSnapshotController extends AbstractController
{
    /** Au-delà, on redirige vers le mode paginé /api/offline/snapshot/{module} plutôt que de tout sérialiser. */
    private const MAX_TOTAL_ENTITIES = 2_000;

    public function __construct(
        private readonly OfflineSnapshotDataProvider $dataProvider,
        private readonly SerializerInterface $serializer,
        private readonly Security $security,
        #[Autowire(service: 'limiter.offline_snapshot')]
        private readonly RateLimiterFactory $offlineSnapshotLimiter,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($this->security->isGranted('ROLE_SUPER_ADMIN')) {
            throw $this->createAccessDeniedException('offline_snapshot.super_admin_not_supported');
        }

        $limit = $this->offlineSnapshotLimiter->create((string) $user->getId())->consume(1);
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(
                $limit->getRetryAfter()->getTimestamp() - time(),
                'offline_snapshot.rate_limited',
            );
        }

        $since = $this->parseSince($request);

        $requestedModules = $this->parseModules($request);
        $accessible        = $this->dataProvider->accessibleEntityModules();
        $entityModules      = array_values(array_intersect($requestedModules, $accessible));
        $wantSites          = in_array('sites', $requestedModules, true);
        $wantReferentiel    = in_array('referentiel', $requestedModules, true);

        $this->guardPayloadSize($entityModules, $wantSites, $since);

        $payload = [
            'generatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'userId'      => $user->getId(),
            'since'       => $since?->format(\DateTimeInterface::ATOM),
        ];

        foreach ($entityModules as $module) {
            $items = $this->dataProvider->scopedQueryBuilder($module, $since)->getQuery()->getResult();
            $payload[$module] = json_decode(
                $this->serializer->serialize($items, 'json', ['groups' => [$this->dataProvider->normalizationGroup($module)]]),
                true,
            );
        }

        if ($wantSites) {
            $sites = $this->dataProvider->sitesQueryBuilder($since)->select('e')->getQuery()->getResult();
            $payload['sites'] = json_decode(
                $this->serializer->serialize($sites, 'json', ['groups' => ['site:read']]),
                true,
            );
        }

        if ($wantReferentiel) {
            $payload['referentiel'] = [
                'categoriesRisque'        => $this->dataProvider->categoriesRisque(),
                'installationsEquipements' => $this->dataProvider->installationsEquipements(),
            ];
        }

        if ($since !== null) {
            $tombstones = [];
            foreach ($entityModules as $module) {
                $tombstones[$module] = $this->dataProvider->tombstoneIds($module, $since);
            }
            $payload['tombstones'] = $tombstones;
        }

        $response = new JsonResponse($payload);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
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

    /** @return list<string> */
    private function parseModules(Request $request): array
    {
        $raw = $request->query->get('modules');
        if ($raw === null || trim($raw) === '') {
            return array_merge(OfflineSnapshotDataProvider::ENTITY_MODULES, OfflineSnapshotDataProvider::ALWAYS_ON_MODULES);
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    /** @param list<string> $entityModules */
    private function guardPayloadSize(array $entityModules, bool $wantSites, ?\DateTimeImmutable $since): void
    {
        $total = 0;

        foreach ($entityModules as $module) {
            $total += (int) (clone $this->dataProvider->scopedQueryBuilder($module, $since))
                ->select('COUNT(e.id)')
                ->getQuery()
                ->getSingleScalarResult();
        }

        if ($wantSites) {
            $total += (int) (clone $this->dataProvider->sitesQueryBuilder($since))
                ->select('COUNT(e.id)')
                ->getQuery()
                ->getSingleScalarResult();
        }

        if ($total > self::MAX_TOTAL_ENTITIES) {
            throw new HttpException(
                413,
                'offline_snapshot.too_large_use_paginated_fallback',
            );
        }
    }
}
