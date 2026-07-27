<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\Dashboard\Service\DashboardKpisService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/dashboard/kpis/sites', name: 'dashboard_kpis_sites', methods: ['GET'])]
#[IsGranted('DASHBOARD_KPIS_VIEW')]
class DashboardKpisSitesController extends AbstractController
{
    public function __construct(private readonly DashboardKpisService $service) {}

    public function __invoke(Request $request): JsonResponse
    {
        $periode   = $request->query->get('periode')   ?: null;
        $dateDebut = $this->parseDate($request->query->get('dateDebut'));
        $dateFin   = $this->parseDate($request->query->get('dateFin'));

        $sites = $this->service->computeSites($periode, $dateDebut, $dateFin);

        foreach ($sites as $i => &$site) {
            $site['classement'] = $i + 1;
        }
        unset($site);

        return $this->json($sites);
    }

    private function parseDate(?string $raw): ?\DateTimeImmutable
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $raw);

        return $dt !== false ? $dt : null;
    }
}
