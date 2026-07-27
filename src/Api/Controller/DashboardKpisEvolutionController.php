<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\Dashboard\Service\DashboardKpisService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/dashboard/kpis/evolution', name: 'dashboard_kpis_evolution', methods: ['GET'])]
#[IsGranted('DASHBOARD_KPIS_VIEW')]
class DashboardKpisEvolutionController extends AbstractController
{
    public function __construct(private readonly DashboardKpisService $service) {}

    public function __invoke(Request $request): JsonResponse
    {
        $codeSite = $request->query->get('codeSite') ?: null;
        $nbMois   = max(1, (int) ($request->query->get('nbMois', '6')));

        return $this->json(
            $this->service->computeEvolution($codeSite, $nbMois),
        );
    }
}
