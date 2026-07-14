<?php

declare(strict_types=1);

namespace App\Api\Controller;

use ApiPlatform\Metadata\Get;
use App\Api\Provider\PermitTravailStatsJournalieresProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/permits-travail/suivi/stats-journalieres', name: 'permit_travail_stats_journalieres', methods: ['GET'])]
#[IsGranted('PERMIT_TRAVAIL_SUIVI')]
class PermitTravailStatsJournalieresController extends AbstractController
{
    public function __construct(
        private readonly PermitTravailStatsJournalieresProvider $provider,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $output = $this->provider->provide(
            new Get(uriTemplate: '/permits-travail/suivi/stats-journalieres'),
            [],
            ['request' => $request],
        );

        return $this->json([
            'nbConsultations'    => $output->nbConsultations,
            'nbChangementsStatut' => $output->nbChangementsStatut,
            'nbTelechargements'  => $output->nbTelechargements,
            'permisActifs'       => $output->permisActifs,
        ]);
    }
}
