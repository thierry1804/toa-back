<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Enum\StatutPermitTravail;
use App\Domain\PermitTravail\Repository\PermitTravailGroupeRepository;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Enum\StatutPlanPrevention;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/plans-prevention/by-site', name: 'plans_prevention_by_site', methods: ['GET'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class PlanPreventionBySiteController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PermitTravailGroupeRepository $groupeRepository,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $codeSite = $request->query->getString('codeSite');
        if ($codeSite === '') {
            return $this->json(['plans' => []]);
        }

        /** @var PlanPrevention[] $allPlans */
        $allPlans = $this->entityManager->getRepository(PlanPrevention::class)
            ->findBy(['statut' => StatutPlanPrevention::VALIDE_HSE]);

        $terminalStatuts = [StatutPermitTravail::CLOTURE, StatutPermitTravail::PV_VALIDE];
        $result = [];

        foreach ($allPlans as $plan) {
            $planifSites = $plan->getPlanificationSites();
            $siteCodes   = array_column($planifSites, 'codeSite');

            $matchesSite = in_array($codeSite, $siteCodes, true)
                || $plan->getCodeSite() === $codeSite;

            if (!$matchesSite) {
                continue;
            }

            $isNouveauSite = $plan->getTypeIntervention() === 'NOUVEAU_SITE';
            $groupe = $isNouveauSite
                ? $this->groupeRepository->findByCodeSiteAndPlan($codeSite, $plan)
                : null;

            if ($isNouveauSite) {
                // Nouveau site : le plan reste sélectionnable tant que la paire
                // Général + H/E n'est pas complète (2 permis obligatoires).
                if ($groupe !== null && $groupe->getPermitSpecialise() !== null) {
                    continue;
                }
            } else {
                $existingPermits = $this->entityManager->getRepository(PermitTravail::class)
                    ->findBy(['codeSite' => $codeSite, 'planPrevention' => $plan]);

                $hasActivePermit = false;
                foreach ($existingPermits as $permit) {
                    if (!in_array($permit->getStatut(), $terminalStatuts, true)) {
                        $hasActivePermit = true;
                        break;
                    }
                }

                if ($hasActivePermit) {
                    continue;
                }
            }

            $resolvedSites = $plan->getPlanificationSites();
            if (empty($resolvedSites)) {
                $resolvedSites = [['codeSite' => $plan->getCodeSite(), 'nomSite' => $plan->getLocalite()]];
            }

            $result[] = [
                'id'                => $plan->getId()?->toRfc4122(),
                'reference'         => $plan->getReference(),
                'activitePlanifiee' => $plan->getActivitePlanifiee(),
                'dateDebut'         => $plan->getDateDebut()?->format('Y-m-d'),
                'dateFin'           => $plan->getDateFin()?->format('Y-m-d'),
                'typeIntervention'  => $plan->getTypeIntervention(),
                'process'           => $plan->getProcess(),
                'permitGeneralId'   => $groupe?->getPermitGeneral()?->getId()?->toRfc4122(),
                'planificationSites' => $resolvedSites,
                'risques'           => array_map(
                    static fn ($r): array => [
                        'id'                => $r->getId()?->toRfc4122(),
                        'description'       => $r->getDescription(),
                        'gravite'           => $r->getGravite(),
                        'probabilite'       => $r->getProbabilite(),
                        'niveauRisque'      => $r->getNiveauRisque(),
                        'mesuresPreventives' => $r->getMesuresPreventives(),
                        'tachePlanifieeId'  => $r->getTachePlanifieeId(),
                        'risqueResiduel'    => $r->getRisqueResiduel(),
                    ],
                    $plan->getRisques()->toArray(),
                ),
                'planificationSections' => $plan->getPlanificationSections(),
                'codeSite'          => $plan->getCodeSite(),
                'localite'          => $plan->getLocalite(),
                'installations'     => $plan->getInstallations(),
                'equipements'       => $plan->getEquipements(),
            ];
        }

        return $this->json(['plans' => $result]);
    }
}
