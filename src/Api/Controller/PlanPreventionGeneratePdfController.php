<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Entity\PlanPreventionPdf;
use App\Domain\PlanPrevention\Enum\StatutPlanPrevention;
use App\Domain\PlanPrevention\Enum\StatutPlanPreventionPdf;
use App\Domain\PlanPrevention\Message\GeneratePlanPreventionPdfMessage;
use App\Domain\PlanPrevention\Repository\PlanPreventionPdfRepository;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

class PlanPreventionGeneratePdfController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PlanPreventionPdfRepository $pdfRepository,
        private readonly MessageBusInterface $messageBus,
    ) {}

    #[Route(
        '/api/plans-prevention/{planId}/generate-pdf',
        name: 'plan_prevention_generate_pdf',
        methods: ['POST'],
    )]
    public function __invoke(string $planId): JsonResponse
    {
        $plan = $this->entityManager->find(PlanPrevention::class, $planId);
        if (!$plan instanceof PlanPrevention) {
            throw $this->createNotFoundException('plan_prevention.not_found');
        }

        $this->denyAccessUnlessGranted('PLAN_PREVENTION_GENERATE_PDF', $plan);

        if ($plan->getStatut() !== StatutPlanPrevention::VALIDE_HSE) {
            throw new UnprocessableEntityHttpException(
                'PDF disponible uniquement pour un plan validé',
            );
        }

        /** @var User $user */
        $user = $this->getUser();

        $existing = $this->pdfRepository->findByPlanPreventionId($planId);
        if ($existing !== null) {
            $existing->setStatut(StatutPlanPreventionPdf::EN_COURS);
            $existing->setFilePath(null);
            $existing->setGenereAt(null);
            $existing->setTailleFichier(null);
            $jobId = Uuid::v4()->toRfc4122();
            $existing->setJobId($jobId);
            $this->entityManager->flush();
        } else {
            $jobId = Uuid::v4()->toRfc4122();
            $pdfRecord = new PlanPreventionPdf();
            $pdfRecord->setPlanPrevention($plan);
            $pdfRecord->setGenerePar($user);
            $pdfRecord->setJobId($jobId);
            $pdfRecord->setStatut(StatutPlanPreventionPdf::EN_COURS);
            $this->entityManager->persist($pdfRecord);
            $this->entityManager->flush();
        }

        $this->messageBus->dispatch(new GeneratePlanPreventionPdfMessage(
            planPreventionId: (string) $plan->getId(),
            jobId: $jobId,
            requesterId: $user->getId(),
        ));

        return new JsonResponse(['jobId' => $jobId, 'statut' => 'EN_COURS'], Response::HTTP_ACCEPTED);
    }
}
