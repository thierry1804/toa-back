<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\User\Entity\User;
use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Enum\TypePermitTravail;
use App\Domain\PermitTravail\Repository\PermitTravailGroupeRepository;
use App\Domain\PlanPrevention\Entity\ModeOperatoirePlanPrevention;
use App\Domain\PlanPrevention\Entity\PhasePlanPrevention;
use App\Domain\Entreprise\Service\AgentProjetScope;
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
        private readonly AgentProjetScope $agentScope,
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

        $result = [];

        foreach ($allPlans as $plan) {
            $planifSites = $plan->getPlanificationSites();
            $siteCodes   = array_column($planifSites, 'codeSite');

            $matchesSite = in_array($codeSite, $siteCodes, true)
                || $plan->getCodeSite() === $codeSite;

            if (!$matchesSite || !$this->visibleToCurrentUser($plan)) {
                continue;
            }

            $isNouveauSite = $plan->getTypeIntervention() === 'Nouveau site';
            $groupe = $isNouveauSite
                ? $this->groupeRepository->findByCodeSiteAndPlan($codeSite, $plan)
                : null;

            $permitGeneralId = null;

            if ($isNouveauSite) {
                // Nouveau site : le plan reste sélectionnable tant que la paire
                // Général + H/E n'est pas complète (2 permis obligatoires).
                if ($groupe !== null && $groupe->getPermitSpecialise() !== null) {
                    continue;
                }

                $permitGeneralId = $groupe?->getPermitGeneral()?->getId()?->toRfc4122();
            } else {
                // Autres processus (VDR/Infra/Energie/Maintenance) : aucune restriction.
                // Le plan reste sélectionnable quels que soient les permis déjà créés
                // (statut inclus) : on peut enchaîner Général / Électrique / Hauteur
                // sans attendre la validation d'un autre permis du même site.
                // permitGeneralId reste informatif pour le front (Nouveau site uniquement
                // s'en sert pour le séquençage) ; hors Nouveau site tous les types
                // restent proposés.
                $dernierGeneral = $this->entityManager->getRepository(PermitTravail::class)
                    ->findBy(
                        ['codeSite' => $codeSite, 'planPrevention' => $plan, 'type' => TypePermitTravail::GENERAL],
                        ['createdAt' => 'DESC'],
                        1,
                    );

                $permitGeneralId = ($dernierGeneral[0] ?? null)?->getId()?->toRfc4122();
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
                'permitGeneralId'   => $permitGeneralId,
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
                'sections'          => array_map(
                    static fn (PhasePlanPrevention $phase): array => [
                        'id'      => $phase->getId()?->toRfc4122(),
                        'libelle' => $phase->getLibelle(),
                        'ordre'   => $phase->getOrdre(),
                        'taches'  => array_map(
                            static fn (ModeOperatoirePlanPrevention $mode): array => [
                                'id'       => $mode->getId()?->toRfc4122(),
                                'ordre'    => $mode->getOrdre(),
                                'tache'    => $mode->getTache(),
                                'materiel' => $mode->getMateriel(),
                                'qui'      => $mode->getQui(),
                            ],
                            $phase->getModesOperatoires()->toArray(),
                        ),
                    ],
                    $plan->getSections()->toArray(),
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

    private function visibleToCurrentUser(PlanPrevention $plan): bool
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return false;
        }

        foreach (['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_DIRECTION', 'ROLE_HSE', 'ROLE_CHEF_PROJET'] as $role) {
            if (in_array($role, $user->getRoles(), true)) {
                return true;
            }
        }

        $userEntreprise = $user->getEntreprise()?->getId();
        $planEntreprise = $plan->getCreatedBy()?->getEntreprise()?->getId();
        if ($userEntreprise === null || $planEntreprise === null || (string) $userEntreprise !== (string) $planEntreprise) {
            return false;
        }

        return $this->agentScope->allows($user, $plan->getPlanificationId());
    }
}
