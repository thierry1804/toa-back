<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\PermitTravail\Repository\KpiInterventionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/interventions/kpis', name: 'intervention_kpis', methods: ['GET'])]
#[IsGranted('INTERVENTION_SUIVI_DASHBOARD')]
class InterventionKpisController extends AbstractController
{
    public function __construct(
        private readonly KpiInterventionRepository $kpiRepository,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $codeSite = $request->query->get('codeSite', '');
        $periode  = $request->query->get('periode', (new \DateTimeImmutable())->format('Y-m'));

        if ($codeSite !== '') {
            $kpi = $this->kpiRepository->findBySiteAndPeriode($codeSite, $periode);
        } else {
            $kpi = $this->kpiRepository->findGlobalForPeriode($periode);
        }

        if ($kpi === null) {
            return $this->json([
                'nbPermisCloturesValides'  => 0,
                'nbPermisCloturesTotal'    => 0,
                'tauxCloture'              => 0.0,
                'delaiMoyenValidationCdp'  => 0.0,
            ]);
        }

        return $this->json([
            'nbPermisCloturesValides'  => $kpi->getNbPermisCloturesValides(),
            'nbPermisCloturesTotal'    => $kpi->getNbPermisCloturesTotal(),
            'tauxCloture'              => $kpi->getTauxCloture(),
            'delaiMoyenValidationCdp'  => $kpi->getDelaiMoyenValidationCdp(),
        ]);
    }
}
