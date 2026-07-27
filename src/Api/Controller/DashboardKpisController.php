<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\Dashboard\Service\DashboardKpisService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/dashboard/kpis', name: 'dashboard_kpis', methods: ['GET'])]
#[IsGranted('DASHBOARD_KPIS_VIEW')]
class DashboardKpisController extends AbstractController
{
    public function __construct(private readonly DashboardKpisService $service) {}

    public function __invoke(Request $request): JsonResponse
    {
        $codeSite   = $request->query->get('codeSite') ?: null;
        $periode    = $request->query->get('periode')  ?: null;
        $dateDebut  = $this->parseDate($request->query->get('dateDebut'));
        $dateFin    = $this->parseDate($request->query->get('dateFin'));

        return $this->json(
            $this->service->computeGlobal($codeSite, $periode, $dateDebut, $dateFin),
        );
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
