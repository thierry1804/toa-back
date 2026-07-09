<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Repository\PlanPreventionPdfRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class PlanPreventionPdfStatusController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PlanPreventionPdfRepository $pdfRepository,
    ) {}

    #[Route(
        '/api/plans-prevention/{planId}/pdf/status',
        name: 'plan_prevention_pdf_status',
        methods: ['GET'],
    )]
    public function __invoke(string $planId): JsonResponse
    {
        $plan = $this->entityManager->find(PlanPrevention::class, $planId);
        if (!$plan instanceof PlanPrevention) {
            throw $this->createNotFoundException('plan_prevention.not_found');
        }

        $this->denyAccessUnlessGranted('PLAN_PREVENTION_VIEW', $plan);

        $pdfRecord = $this->pdfRepository->findByPlanPreventionId($planId);
        if ($pdfRecord === null) {
            return new JsonResponse(['statut' => null, 'jobId' => null]);
        }

        return new JsonResponse([
            'statut'        => $pdfRecord->getStatut()->value,
            'jobId'         => $pdfRecord->getJobId(),
            'genereAt'      => $pdfRecord->getGenereAt()?->format(\DateTimeInterface::ATOM),
            'tailleFichier' => $pdfRecord->getTailleFichier(),
        ]);
    }
}
