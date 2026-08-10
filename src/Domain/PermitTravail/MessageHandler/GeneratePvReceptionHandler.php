<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\MessageHandler;

use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Enum\ActionPermitTravailLog;
use App\Domain\PermitTravail\Enum\StatutPvReceptionPdf;
use App\Domain\PermitTravail\Message\GeneratePvReceptionMessage;
use App\Domain\PermitTravail\Repository\CloturePermRepository;
use App\Domain\PermitTravail\Repository\PvReceptionPdfRepository;
use App\Domain\PermitTravail\Service\PermitTravailLogService;
use App\Domain\PlanPrevention\Service\GotenbergPdfService;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Twig\Environment;

#[AsMessageHandler]
final class GeneratePvReceptionHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PvReceptionPdfRepository $pvRepository,
        private readonly CloturePermRepository $clotureRepository,
        private readonly GotenbergPdfService $gotenbergPdfService,
        private readonly Environment $twig,
        private readonly PermitTravailLogService $logService,
        #[Autowire('@default.storage')]
        private readonly FilesystemOperator $storage,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {}

    public function __invoke(GeneratePvReceptionMessage $message): void
    {
        $pvRecord = $this->pvRepository->findByJobId($message->jobId);
        if ($pvRecord === null) {
            $this->logger->error('PvReceptionPdf record not found for jobId {jobId}', [
                'jobId' => $message->jobId,
            ]);
            return;
        }

        try {
            $permit = $this->entityManager->find(PermitTravail::class, $message->permitTravailId);
            if ($permit === null) {
                throw new \RuntimeException('PermitTravail not found: ' . $message->permitTravailId);
            }

            $cloture = $this->clotureRepository->findByPermitTravailId($message->permitTravailId);

            // Pre-load lazy associations needed by the Twig template
            $planPrevention = $permit->getPlanPrevention();
            if ($planPrevention !== null) {
                $planPrevention->getChefProjet(); // force hydration
            }
            $permit->getCreatedBy(); // force hydration

            $now        = new \DateTimeImmutable();
            $logoPath   = $this->projectDir . '/public/images/toa_logo.png';
            $logoBase64 = is_file($logoPath)
                ? base64_encode((string) file_get_contents($logoPath))
                : null;

            $html = $this->twig->render('pdf/pv_reception_pdf.html.twig', [
                'permit'      => $permit,
                'cloture'     => $cloture,
                'generatedAt' => $now,
                'logoBase64'  => $logoBase64,
            ]);

            $pdfContent = $this->gotenbergPdfService->htmlToPdf($html);

            $reference = $permit->getReference() ?? $permit->getId()->toRfc4122();
            $sanitized = preg_replace('/[^a-zA-Z0-9\-]/', '_', $reference);
            $filePath  = sprintf(
                'permits-travail-pv/%s/%s/pv_%s.pdf',
                $now->format('Y'),
                $sanitized,
                $now->format('U'),
            );

            $this->storage->write($filePath, $pdfContent);
            $size = strlen($pdfContent);

            $pvRecord->setFilePath($filePath);
            $pvRecord->setGenereAt($now);
            $pvRecord->setTailleFichier($size);
            $pvRecord->setStatut(StatutPvReceptionPdf::GENERE);

            $this->entityManager->flush();

            $this->logger->info('PV réception PDF generated', [
                'permitId' => $message->permitTravailId,
                'userId'   => $message->requesterId,
                'fileSize' => $size,
                'filePath' => $filePath,
            ]);

            $requester = $this->entityManager->find(User::class, $message->requesterId);
            if ($requester instanceof User) {
                try {
                    $this->logService->log(
                        $permit,
                        ActionPermitTravailLog::PDF_GENERE,
                        $requester,
                        ['type' => 'pv_reception', 'filePath' => $filePath, 'tailleFichier' => $size],
                    );
                } catch (\Throwable) {
                }
            }
        } catch (\Throwable $e) {
            $pvRecord->setStatut(StatutPvReceptionPdf::ERREUR);
            $this->entityManager->flush();

            $this->logger->error('PV réception PDF generation failed for permit {permitId}: {error}', [
                'permitId' => $message->permitTravailId,
                'error'    => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
