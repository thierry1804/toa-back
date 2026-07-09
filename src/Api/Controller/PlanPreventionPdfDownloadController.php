<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Enum\StatutPlanPreventionPdf;
use App\Domain\PlanPrevention\Repository\PlanPreventionPdfRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToReadFile;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class PlanPreventionPdfDownloadController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PlanPreventionPdfRepository $pdfRepository,
        #[Autowire('@default.storage')]
        private readonly FilesystemOperator $storage,
    ) {}

    #[Route(
        '/api/plans-prevention/{planId}/pdf/download',
        name: 'plan_prevention_pdf_download',
        methods: ['GET'],
    )]
    public function __invoke(string $planId): StreamedResponse
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
            throw new NotFoundHttpException('plan_prevention_pdf.file_not_found');
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
