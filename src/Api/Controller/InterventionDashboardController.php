<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\Intervention\Enum\StatutIntervention;
use App\Domain\Intervention\Repository\InterventionRepository;
use App\Domain\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/api/interventions/dashboard', name: 'intervention_dashboard', methods: ['GET'])]
#[IsGranted('INTERVENTION_SUIVI_DASHBOARD')]
class InterventionDashboardController extends AbstractController
{
    public function __construct(
        private readonly InterventionRepository $interventionRepository,
        private readonly Security $security,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user  = $this->security->getUser();
        $isHse = $this->security->isGranted('ROLE_HSE')
               || $this->security->isGranted('ROLE_SUPER_ADMIN')
               || $this->security->isGranted('ROLE_ADMIN');

        $codeSite     = $request->query->get('codeSite');
        $statut       = $request->query->get('statut');
        $dateDebut    = $this->parseDate($request->query->get('dateDebut'));
        $dateFin      = $this->parseDate($request->query->get('dateFin'));

        $rows = $this->interventionRepository->getDashboardData(
            $user, $isHse, $codeSite, $dateDebut, $dateFin, $statut,
        );

        $parStatut = [
            StatutIntervention::EN_PREPARATION->value      => 0,
            StatutIntervention::EVALUATION_COMPLETE->value => 0,
            StatutIntervention::EN_COURS->value            => 0,
            StatutIntervention::TERMINEE->value            => 0,
        ];

        $totalAvancement = 0.0;
        $countWithSuivi  = 0;
        $interventions   = [];

        foreach ($rows as $row) {
            $statutVal = $row['statut'] instanceof StatutIntervention
                ? $row['statut']->value
                : (string) $row['statut'];

            if (array_key_exists($statutVal, $parStatut)) {
                $parStatut[$statutVal]++;
            }

            $avg = (float) ($row['avancementMoyen'] ?? 0);
            if ($avg > 0 || (int) ($row['nbSuivis'] ?? 0) > 0) {
                $totalAvancement += $avg;
                $countWithSuivi++;
            }

            $dernierSuivi = $row['dernierSuivi'];
            if ($dernierSuivi instanceof \DateTimeInterface) {
                $dernierSuivi = $dernierSuivi->format('Y-m-d');
            }

            $id = $row['id'];
            if ($id instanceof Uuid) {
                $id = $id->toRfc4122();
            }

            $interventions[] = [
                'id'              => (string) $id,
                'permitReference' => $row['permitReference'],
                'codeSite'        => $row['codeSite'],
                'statut'          => $statutVal,
                'avancementMoyen' => round($avg, 2),
                'dernierSuivi'    => $dernierSuivi,
                'nbSuivis'        => (int) ($row['nbSuivis'] ?? 0),
            ];
        }

        $avancementMoyen = $countWithSuivi > 0
            ? round($totalAvancement / $countWithSuivi, 2)
            : 0.0;

        return $this->json([
            'totalInterventions' => count($rows),
            'parStatut'          => $parStatut,
            'avancementMoyen'    => $avancementMoyen,
            'interventions'      => $interventions,
        ]);
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
