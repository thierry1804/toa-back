<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\Intervention\Entity\SuiviJournalier;
use App\Domain\Intervention\Entity\SuiviJournalierDocument;
use App\Security\Voter\SuiviJournalierVoter;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToReadFile;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class SuiviJournalierDocumentDownloadController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('@default.storage')]
        private readonly FilesystemOperator $storage,
    ) {}

    #[Route(
        '/api/suivis-journaliers/{suiviJournalierId}/documents/{documentId}/download',
        name: 'suivi_journalier_document_download',
        methods: ['GET'],
    )]
    public function __invoke(string $suiviJournalierId, string $documentId): StreamedResponse
    {
        $suivi = $this->entityManager->find(SuiviJournalier::class, $suiviJournalierId);
        if (!$suivi instanceof SuiviJournalier) {
            throw new NotFoundHttpException('suivi_journalier.not_found');
        }

        $this->denyAccessUnlessGranted(SuiviJournalierVoter::VIEW, $suivi);

        $document = $this->entityManager->find(SuiviJournalierDocument::class, $documentId);
        if (!$document instanceof SuiviJournalierDocument
            || (string) $document->getSuiviJournalier()?->getId() !== (string) $suivi->getId()
        ) {
            throw new NotFoundHttpException('suivi_journalier_document.not_found');
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
