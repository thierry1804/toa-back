<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\MessageHandler;

use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Enum\ActionPermitTravailLog;
use App\Domain\PermitTravail\Enum\StatutPermitTravailPdf;
use App\Domain\PermitTravail\Enum\TypePermitTravail;
use App\Domain\PermitTravail\Message\GeneratePermitTravailPdfMessage;
use App\Domain\PermitTravail\Repository\PermitTravailPdfRepository;
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
final class GeneratePermitTravailPdfHandler
{
    private const TEMPLATE_MAP = [
        TypePermitTravail::GENERAL->value    => 'pdf/permit_general_pdf.html.twig',
        TypePermitTravail::ELECTRIQUE->value => 'pdf/permit_electrique_pdf.html.twig',
        TypePermitTravail::HAUTEUR->value    => 'pdf/permit_hauteur_pdf.html.twig',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PermitTravailPdfRepository $pdfRepository,
        private readonly GotenbergPdfService $gotenbergPdfService,
        private readonly Environment $twig,
        private readonly PermitTravailLogService $logService,
        #[Autowire('@default.storage')]
        private readonly FilesystemOperator $storage,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {}

    public function __invoke(GeneratePermitTravailPdfMessage $message): void
    {
        $pdfRecord = $this->pdfRepository->findByJobId($message->jobId);
        if ($pdfRecord === null) {
            $this->logger->error('PermitTravailPdf record not found for jobId {jobId}', [
                'jobId' => $message->jobId,
            ]);
            return;
        }

        try {
            $permit = $this->entityManager->find(PermitTravail::class, $message->permitTravailId);
            if ($permit === null) {
                throw new \RuntimeException('PermitTravail not found: ' . $message->permitTravailId);
            }

            $permit->getDocuments()->toArray();
            $permit->getDecisionsHse()->toArray();

            $typePermis = $permit->getType() ?? TypePermitTravail::GENERAL;
            // @phpstan-ignore nullCoalesce.offset (repli défensif si un nouveau type de permis est ajouté sans template)
            $template   = self::TEMPLATE_MAP[$typePermis->value] ?? self::TEMPLATE_MAP[TypePermitTravail::GENERAL->value];

            $now = new \DateTimeImmutable();
            $logoPath   = $this->projectDir . '/public/images/toa_logo.png';
            $logoBase64 = is_file($logoPath)
                ? base64_encode((string) file_get_contents($logoPath))
                : null;

            $html = $this->twig->render($template, [
                'permit'      => $permit,
                'generatedAt' => $now,
                'logoBase64'  => $logoBase64,
            ]);

            $pdfContent = $this->gotenbergPdfService->htmlToPdf($html);

            $reference = $permit->getReference() ?? $permit->getId()->toRfc4122();
            $sanitized = preg_replace('/[^a-zA-Z0-9\-]/', '_', $reference);
            $filePath  = sprintf(
                'permits-travail-pdf/%s/%s/permit_%s.pdf',
                $now->format('Y'),
                $sanitized,
                $now->format('U'),
            );

            $this->storage->write($filePath, $pdfContent);
            $size = strlen($pdfContent);

            $pdfRecord->setFilePath($filePath);
            $pdfRecord->setGenereAt($now);
            $pdfRecord->setTailleFichier($size);
            $pdfRecord->setStatut(StatutPermitTravailPdf::GENERE);

            $this->entityManager->flush();

            $this->logger->info('Permit travail PDF generated', [
                'permitId' => $message->permitTravailId,
                'userId'   => $message->requesterId,
                'fileSize' => $size,
                'filePath' => $filePath,
            ]);

            $requester = $this->entityManager->find(\App\Domain\User\Entity\User::class, $message->requesterId);
            if ($requester instanceof User) {
                try {
                    $this->logService->log(
                        $permit,
                        ActionPermitTravailLog::PDF_GENERE,
                        $requester,
                        ['filePath' => $filePath, 'tailleFichier' => $size],
                    );
                } catch (\Throwable) {
                    // Do not fail PDF generation if logging fails
                }
            }
        } catch (\Throwable $e) {
            $pdfRecord->setStatut(StatutPermitTravailPdf::ERREUR);
            $this->entityManager->flush();

            $this->logger->error('PDF generation failed for permit {permitId}: {error}', [
                'permitId' => $message->permitTravailId,
                'error'    => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
