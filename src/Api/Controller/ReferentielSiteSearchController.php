<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\Referentiel\Repository\SiteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/referentiel/sites/search', name: 'referentiel_site_search', methods: ['GET'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ReferentielSiteSearchController extends AbstractController
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $q     = trim((string) $request->query->get('q', ''));
        $limit = max(1, min(20, (int) $request->query->get('limit', 10)));

        if ($q === '') {
            return $this->json([]);
        }

        return $this->json($this->siteRepository->search($q, $limit));
    }
}
