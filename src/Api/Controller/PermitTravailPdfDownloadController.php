<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Enum\StatutPermitTravailPdf;
use App\Domain\PermitTravail\Repository\PermitTravailPdfRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToReadFile;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class PermitTravailPdfDownloadController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PermitTravailPdfRepository $pdfRepository,
        #[Autowire('@default.storage')]
        private readonly FilesystemOperator $storage,
    ) {}

    #[Route(
        '/api/permits-travail/{permitId}/pdf/download',
        name: 'permit_travail_pdf_download',
        methods: ['GET'],
    )]
    public function __invoke(string $permitId): StreamedResponse
    {
        $permit = $this->entityManager->find(PermitTravail::class, $permitId);
        if (!$permit instanceof PermitTravail) {
            throw new NotFoundHttpException('permit_travail.not_found');
        }

        $this->denyAccessUnlessGranted('PERMIT_TRAVAIL_VIEW', $permit);

        $pdfRecord = $this->pdfRepository->findByPermitTravailId($permitId);
        if ($pdfRecord === null || $pdfRecord->getStatut() !== StatutPermitTravailPdf::GENERE) {
            throw new NotFoundHttpException('permit_travail_pdf.not_ready');
        }

        $filePath  = $pdfRecord->getFilePath();
        $reference = $permit->getReference() ?? 'permit_travail';
        $filename  = preg_replace('/[^a-zA-Z0-9\-]/', '_', $reference) . '.pdf';

        try {
            $stream = $this->storage->readStream($filePath);
        } catch (UnableToReadFile) {
            throw new NotFoundHttpException('permit_travail_pdf.file_not_found');
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
