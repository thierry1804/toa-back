<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Entity\PermitTravailDocument;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToReadFile;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class PermitTravailDocumentDownloadController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('@default.storage')]
        private readonly FilesystemOperator $storage,
    ) {}

    #[Route(
        '/api/permits-travail/{permitTravailId}/documents/{documentId}/download',
        name: 'permit_travail_document_download',
        methods: ['GET'],
    )]
    public function __invoke(string $permitTravailId, string $documentId): StreamedResponse
    {
        $permit = $this->entityManager->find(PermitTravail::class, $permitTravailId);
        if (!$permit instanceof PermitTravail) {
            throw new NotFoundHttpException('permit_travail.not_found');
        }

        $this->denyAccessUnlessGranted('PERMIT_TRAVAIL_VIEW', $permit);

        $document = $this->entityManager->find(PermitTravailDocument::class, $documentId);
        if (!$document instanceof PermitTravailDocument
            || (string) $document->getPermitTravail()?->getId() !== (string) $permit->getId()
        ) {
            throw new NotFoundHttpException('permit_travail_document.not_found');
        }

        $filePath = $document->getFilePath();
        $mimeType = $document->getMimeType() ?: 'application/octet-stream';
        $filename = basename($filePath);

        try {
            $stream = $this->storage->readStream($filePath);
        } catch (UnableToReadFile) {
            throw new NotFoundHttpException('document_file_not_found');
        }

        return new StreamedResponse(static function () use ($stream): void {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type'        => $mimeType,
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }
}
