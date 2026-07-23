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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/api/interventions/stats-journalieres', name: 'intervention_stats_journalieres', methods: ['GET'])]
#[IsGranted('INTERVENTION_SUIVI_DASHBOARD')]
class InterventionStatsJournalieresController extends AbstractController
{
    public function __construct(
        private readonly InterventionRepository $interventionRepository,
        private readonly Security $security,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $codeSite = $request->query->get('codeSite');

        if ($codeSite === null || $codeSite === '') {
            return $this->json(['error' => 'Le paramètre codeSite est obligatoire'], Response::HTTP_BAD_REQUEST);
        }

        $dateStr = $request->query->get('date');
        $date    = null;
        if ($dateStr !== null && $dateStr !== '') {
            try {
                $date = new \DateTimeImmutable($dateStr);
            } catch (\Throwable) {
                $date = null;
            }
        }
        $date ??= new \DateTimeImmutable('today');

        /** @var User $user */
        $user  = $this->security->getUser();
        $isHse = $this->security->isGranted('ROLE_HSE')
               || $this->security->isGranted('ROLE_SUPER_ADMIN')
               || $this->security->isGranted('ROLE_ADMIN');

        $rows = $this->interventionRepository->getActiveInterventionsBySite(
            $codeSite, $user, $isHse,
        );

        $nbSuivisJour = $this->interventionRepository->countSuivisForDate(
            $date, $codeSite, $user, $isHse,
        );

        $totalAvancement = 0.0;
        $countWithSuivi  = 0;
        $interventions   = [];

        foreach ($rows as $row) {
            $statutVal = $row['statut'] instanceof StatutIntervention
                ? $row['statut']->value
                : (string) $row['statut'];

            $avg = (float) ($row['avancementMoyen'] ?? 0);
            $totalAvancement += $avg;
            $countWithSuivi++;

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
                'nbSuivis'        => (int) ($row['nbSuivis'] ?? 0),
            ];
        }

        $avancementMoyen = $countWithSuivi > 0
            ? round($totalAvancement / $countWithSuivi, 2)
            : 0.0;

        return $this->json([
            'date'                  => $date->format('Y-m-d'),
            'codeSite'              => $codeSite,
            'nbInterventionsActives' => count($rows),
            'avancementMoyen'       => $avancementMoyen,
            'nbSuivisJour'          => $nbSuivisJour,
            'interventions'         => $interventions,
        ]);
    }
}
