<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Enum\StatutPvReceptionPdf;
use App\Domain\PermitTravail\Repository\PvReceptionPdfRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToReadFile;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/api/permits-travail/{permitId}/pv/download',
    name: 'permit_travail_pv_download',
    requirements: ['permitId' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'],
    methods: ['GET'],
)]
class PvReceptionPdfDownloadController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PvReceptionPdfRepository $pvRepository,
        #[Autowire('@default.storage')]
        private readonly FilesystemOperator $storage,
    ) {}

    public function __invoke(string $permitId): StreamedResponse
    {
        $permit = $this->entityManager->find(PermitTravail::class, $permitId);
        if (!$permit instanceof PermitTravail) {
            throw new NotFoundHttpException('permit_travail.not_found');
        }

        $this->denyAccessUnlessGranted('PERMIT_TRAVAIL_VIEW', $permit);

        $pvRecord = $this->pvRepository->findByPermitTravailId($permitId);
        if ($pvRecord === null || $pvRecord->getStatut() !== StatutPvReceptionPdf::GENERE) {
            throw new NotFoundHttpException('pv_reception_pdf.not_ready');
        }

        $filePath  = $pvRecord->getFilePath();
        $reference = $permit->getReference() ?? 'permit_travail';
        $filename  = 'pv_' . preg_replace('/[^a-zA-Z0-9\-]/', '_', $reference) . '.pdf';

        try {
            $stream = $this->storage->readStream($filePath);
        } catch (UnableToReadFile) {
            throw new NotFoundHttpException('pv_reception_pdf.file_not_found');
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
