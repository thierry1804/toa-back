<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Enum\StatutPermitTravailPdf;
use App\Domain\PermitTravail\Message\GeneratePermitTravailPdfMessage;
use App\Domain\PermitTravail\Repository\PermitTravailPdfRepository;
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

class PermitTravailPdfDownloadController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PermitTravailPdfRepository $pdfRepository,
        #[Autowire('@default.storage')]
        private readonly FilesystemOperator $storage,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {}

    #[Route(
        '/api/permits-travail/{permitId}/pdf/download',
        name: 'permit_travail_pdf_download',
        methods: ['GET'],
    )]
    public function __invoke(string $permitId): Response
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
            $this->logger->warning('permit_travail_pdf.file_missing_regenerating', [
                'permitId' => $permitId,
                'filePath' => $filePath,
            ]);

            /** @var User $user */
            $user = $this->getUser();

            $jobId = Uuid::v4()->toRfc4122();
            $pdfRecord->setStatut(StatutPermitTravailPdf::EN_COURS);
            $pdfRecord->setFilePath(null);
            $pdfRecord->setGenereAt(null);
            $pdfRecord->setTailleFichier(null);
            $pdfRecord->setJobId($jobId);
            $this->entityManager->flush();

            $this->messageBus->dispatch(new GeneratePermitTravailPdfMessage(
                permitTravailId: $permitId,
                jobId: $jobId,
                requesterId: $user->getId(),
            ));

            return new JsonResponse(
                [
                    'message' => 'permit_travail_pdf.regenerating',
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
