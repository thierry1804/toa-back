<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\Intervention\Entity\EvaluationRisque;
use App\Domain\Intervention\Repository\InterventionRepository;
use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
#[Route(
    '/api/interventions/{id}/suivi-hse',
    name: 'intervention_detail_hse',
    requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'],
    methods: ['GET'],
)]
#[IsGranted('INTERVENTION_SUIVI_DETAIL')]
class InterventionDetailHseController extends AbstractController
{
    public function __construct(
        private readonly InterventionRepository $interventionRepository,
        private readonly Security $security,
    ) {}

    public function __invoke(string $id): JsonResponse
    {
        $intervention = $this->interventionRepository->findForHseDetail($id);

        if ($intervention === null) {
            return $this->json(['error' => 'Intervention introuvable'], Response::HTTP_NOT_FOUND);
        }

        $permit = $intervention->getPermitTravail();
        $plan   = $permit?->getPlanPrevention();

        // INTERVENTION_SUIVI_DETAIL (voter) only confirms the actor's role has
        // this action key at all -- it performs no per-record ownership check.
        // Without the guard below, any id (UUID) was consultable by anyone
        // holding that action key, regardless of entreprise/chefProjet
        // (confirmed IDOR). TOA roles with bypass (HSE/ADMIN/SUPER_ADMIN)
        // keep seeing everything; every other role is restricted to its own
        // entreprise's interventions (prestataire-side) or to interventions
        // whose plan de prevention is assigned to them (CP TOA).
        $user  = $this->security->getUser();
        $isHse = $this->security->isGranted('ROLE_HSE')
               || $this->security->isGranted('ROLE_SUPER_ADMIN')
               || $this->security->isGranted('ROLE_ADMIN');

        if (!$isHse && (!$user instanceof User || !$this->hasOwnership($permit, $plan, $user))) {
            // 404 rather than 403: do not disclose that an intervention with
            // this id exists outside the caller's scope.
            return $this->json(['error' => 'Intervention introuvable'], Response::HTTP_NOT_FOUND);
        }

        $suivis = [];
        foreach ($intervention->getSuivis() as $suivi) {
            $docs = [];
            foreach ($suivi->getDocuments() as $doc) {
                $docs[] = [
                    'id'       => (string) $doc->getId(),
                    'nom'      => $doc->getNom(),
                    'mimeType' => $doc->getMimeType(),
                ];
            }

            $suivis[] = [
                'id'                    => (string) $suivi->getId(),
                'date'                  => $suivi->getDate()?->format('Y-m-d'),
                'nomResponsable'        => $suivi->getNomResponsable(),
                'realise'               => $suivi->isRealise(),
                'motifNonRealisation'   => $suivi->getMotifNonRealisation(),
                'avancementPourcentage' => $suivi->getAvancementPourcentage(),
                'commentaire'           => $suivi->getCommentaire(),
                'createdAt'             => $suivi->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                'documents'             => $docs,
            ];
        }

        $evaluations = [];
        $niveauTotal = 0;
        $evalCount   = 0;
        foreach ($intervention->getEvaluations() as $eval) {
            /** @var EvaluationRisque $eval */
            $niveauTotal += $eval->getNiveauRisque();
            $evalCount++;
            $evaluations[] = [
                'id'               => (string) $eval->getId(),
                'description'      => $eval->getDescription(),
                'gravite'          => $eval->getGravite(),
                'probabilite'      => $eval->getProbabilite(),
                'niveauRisque'     => $eval->getNiveauRisque(),
                'niveauCouleur'    => $eval->getNiveauCouleur()->value,
                'mesuresConfirmees' => $eval->getMesuresConfirmees(),
                'estReevalue'      => $eval->isEstReevalue(),
            ];
        }

        $suivisTries = $suivis;
        usort($suivisTries, static fn (array $a, array $b): int => strcmp((string) $b['date'], (string) $a['date']));
        $avancementMoyen = $suivisTries[0]['avancementPourcentage'] ?? 0.0;

        return $this->json([
            'intervention' => [
                'id'        => (string) $intervention->getId(),
                'statut'    => $intervention->getStatut()->value,
                'createdAt' => $intervention->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            ],
            'permitTravail' => $permit === null ? null : [
                'id'        => (string) $permit->getId(),
                'reference' => $permit->getReference(),
                'type'      => $permit->getType()?->value,
                'codeSite'  => $permit->getCodeSite(),
            ],
            'planPrevention' => $plan === null ? null : [
                'id'        => (string) $plan->getId(),
                'reference' => $plan->getReference(),
            ],
            'suiviJournaliers'  => $suivis,
            'evaluationRisques' => [
                'count'        => $evalCount,
                'niveauMoyen'  => $evalCount > 0 ? round($niveauTotal / $evalCount, 2) : 0,
                'items'        => $evaluations,
            ],
            'avancementMoyen' => $avancementMoyen,
        ]);
    }

    private function hasOwnership(?PermitTravail $permit, ?PlanPrevention $plan, User $user): bool
    {
        $entreprise = $user->getEntreprise();
        if ($entreprise !== null) {
            // Prestataire-side roles (ROLE_PRESTATAIRE, ROLE_CHEF_PROJET_PRESTATAIRE,
            // ROLE_AGENT_TERRAIN, ROLE_HSE cote prestataire): same entreprise as the
            // permit's creator, aligned with PermitTravailVoter::hasOwnership() and
            // InterventionRepository::applyOwnershipFilter().
            return $permit?->getCreatedBy()?->getEntreprise()?->getId() === $entreprise->getId();
        }

        // No entreprise: CP TOA, restricted to interventions whose plan de
        // prevention is assigned to them (same rule as the dashboard's
        // pp.chefProjet branch).
        $chefProjet = $plan?->getChefProjet();

        return $chefProjet !== null && $chefProjet->getId() === $user->getId();
    }
}
