<?php

declare(strict_types=1);

namespace App\Domain\PlanPrevention\MessageHandler;

use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Entity\PlanPreventionPdf;
use App\Domain\PlanPrevention\Enum\StatutPlanPreventionPdf;
use App\Domain\PlanPrevention\Message\GeneratePlanPreventionPdfMessage;
use App\Domain\PlanPrevention\Repository\PlanPreventionPdfRepository;
use App\Domain\PlanPrevention\Service\GotenbergPdfService;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Twig\Environment;

#[AsMessageHandler]
final class GeneratePlanPreventionPdfHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PlanPreventionPdfRepository $pdfRepository,
        private readonly GotenbergPdfService $gotenbergPdfService,
        private readonly Environment $twig,
        #[Autowire('@default.storage')]
        private readonly FilesystemOperator $storage,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {}

    public function __invoke(GeneratePlanPreventionPdfMessage $message): void
    {
        $pdfRecord = $this->pdfRepository->findByJobId($message->jobId);
        if ($pdfRecord === null) {
            $this->logger->error('PlanPreventionPdf record not found for jobId {jobId}', [
                'jobId' => $message->jobId,
            ]);
            return;
        }

        try {
            $plan = $this->entityManager->find(PlanPrevention::class, $message->planPreventionId);
            if ($plan === null) {
                throw new \RuntimeException('PlanPrevention not found: ' . $message->planPreventionId);
            }

            $plan->getSites()->toArray();
            $plan->getRisques()->toArray();
            $plan->getDocuments()->toArray();
            $plan->getExamens()->toArray();
            $plan->getDecisionsHse()->toArray();
            $plan->getVersions()->toArray();

            $now = new \DateTimeImmutable();
            $logoPath = $this->projectDir . '/public/images/toa_logo.png';
            $logoBase64 = is_file($logoPath)
                ? base64_encode((string) file_get_contents($logoPath))
                : null;
            $html = $this->twig->render('pdf/plan_prevention_pdf.html.twig', [
                'plan'        => $plan,
                'generatedAt' => $now,
                'logoBase64'  => $logoBase64,
            ]);

            $pdfContent = $this->gotenbergPdfService->htmlToPdf($html);

            $reference   = $plan->getReference() ?? $plan->getId()->toRfc4122();
            $sanitized   = preg_replace('/[^a-zA-Z0-9\-]/', '_', $reference);
            $filePath    = sprintf(
                'plans-prevention-pdf/%s/%s/plan_%s.pdf',
                $now->format('Y'),
                $sanitized,
                $now->format('U'),
            );

            $this->storage->write($filePath, $pdfContent);
            $size = strlen($pdfContent);

            $pdfRecord->setFilePath($filePath);
            $pdfRecord->setGenereAt($now);
            $pdfRecord->setTailleFichier($size);
            $pdfRecord->setStatut(StatutPlanPreventionPdf::GENERE);

            $this->entityManager->flush();

            $this->logger->info('Plan prevention PDF generated', [
                'planId'   => $message->planPreventionId,
                'userId'   => $message->requesterId,
                'fileSize' => $size,
                'filePath' => $filePath,
            ]);
        } catch (\Throwable $e) {
            $pdfRecord->setStatut(StatutPlanPreventionPdf::ERREUR);
            $this->entityManager->flush();

            $this->logger->error('PDF generation failed for plan {planId}: {error}', [
                'planId' => $message->planPreventionId,
                'error'  => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
