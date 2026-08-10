<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\Intervention\Entity\EvaluationRisque;
use App\Domain\Intervention\Repository\InterventionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    ) {}

    public function __invoke(string $id): JsonResponse
    {
        $intervention = $this->interventionRepository->findForHseDetail($id);

        if ($intervention === null) {
            return $this->json(['error' => 'Intervention introuvable'], Response::HTTP_NOT_FOUND);
        }

        $permit = $intervention->getPermitTravail();
        $plan   = $permit?->getPlanPrevention();

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

        $avancementValues = array_filter(
            array_map(fn ($s) => $s['avancementPourcentage'], $suivis),
            fn ($v) => $v !== null,
        );
        $avancementMoyen = count($avancementValues) > 0
            ? round(array_sum($avancementValues) / count($avancementValues), 2)
            : 0.0;

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
}
