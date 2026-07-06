<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\PlanPrevention\Entity\DocumentPrevention;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToReadFile;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class DocumentPreventionDownloadController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('@default.storage')]
        private readonly FilesystemOperator $storage,
    ) {}

    #[Route(
        '/api/plans-prevention/{planId}/documents/{documentId}/download',
        name: 'document_prevention_download',
        methods: ['GET'],
    )]
    public function __invoke(string $planId, string $documentId): StreamedResponse
    {
        $plan = $this->entityManager->find(PlanPrevention::class, $planId);
        if (!$plan instanceof PlanPrevention) {
            throw new NotFoundHttpException('plan_prevention.not_found');
        }

        $this->denyAccessUnlessGranted('PLAN_PREVENTION_VIEW', $plan);

        $document = $this->entityManager->find(DocumentPrevention::class, $documentId);
        if (!$document instanceof DocumentPrevention
            || (string) $document->getPlanPrevention()?->getId() !== (string) $plan->getId()
        ) {
            throw new NotFoundHttpException('document_prevention.not_found');
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
