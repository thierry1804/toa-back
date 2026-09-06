<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Enum\StatutPlanPreventionPdf;
use App\Domain\PlanPrevention\Message\GeneratePlanPreventionPdfMessage;
use App\Domain\PlanPrevention\Repository\PlanPreventionPdfRepository;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToReadFile;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

class PlanPreventionPdfDownloadController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PlanPreventionPdfRepository $pdfRepository,
        #[Autowire('@default.storage')]
        private readonly FilesystemOperator $storage,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {}

    #[Route(
        '/api/plans-prevention/{planId}/pdf/download',
        name: 'plan_prevention_pdf_download',
        methods: ['GET'],
    )]
    public function __invoke(string $planId): Response
    {
        $plan = $this->entityManager->find(PlanPrevention::class, $planId);
        if (!$plan instanceof PlanPrevention) {
            throw new NotFoundHttpException('plan_prevention.not_found');
        }

        $this->denyAccessUnlessGranted('PLAN_PREVENTION_VIEW', $plan);

        $pdfRecord = $this->pdfRepository->findByPlanPreventionId($planId);
        if ($pdfRecord === null || $pdfRecord->getStatut() !== StatutPlanPreventionPdf::GENERE) {
            throw new NotFoundHttpException('plan_prevention_pdf.not_ready');
        }

        $filePath = $pdfRecord->getFilePath();
        $reference = $plan->getReference() ?? 'plan_prevention';
        $filename  = preg_replace('/[^a-zA-Z0-9\-]/', '_', $reference) . '.pdf';

        try {
            $stream = $this->storage->readStream($filePath);
        } catch (UnableToReadFile) {
            $this->logger->warning('plan_prevention_pdf.file_missing_regenerating', [
                'planId'   => $planId,
                'filePath' => $filePath,
            ]);

            /** @var User $user */
            $user = $this->getUser();

            $jobId = Uuid::v4()->toRfc4122();
            $pdfRecord->setStatut(StatutPlanPreventionPdf::EN_COURS);
            $pdfRecord->setFilePath(null);
            $pdfRecord->setGenereAt(null);
            $pdfRecord->setTailleFichier(null);
            $pdfRecord->setJobId($jobId);
            $this->entityManager->flush();

            $this->messageBus->dispatch(new GeneratePlanPreventionPdfMessage(
                planPreventionId: $planId,
                jobId: $jobId,
                requesterId: $user->getId(),
            ));

            return new JsonResponse(
                [
                    'message' => 'plan_prevention_pdf.regenerating',
                    'jobId'   => $jobId,
                    'statut'  => 'EN_COURS',
                ],
                Response::HTTP_ACCEPTED,
            );
        }

        return new StreamedResponse(static function () use ($stream): void {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            'Cache-Control'       => 'private, no-store',
        ]);
    }
}
