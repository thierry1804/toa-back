<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Support\UploadRules;
use App\Domain\Intervention\Entity\SuiviJournalier;
use App\Domain\Intervention\Entity\SuiviJournalierDocument;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class SuiviJournalierDocumentUploadProcessor implements ProcessorInterface
{
    private const MAX_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB

    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        #[Autowire('@default.storage')]
        private readonly FilesystemOperator $storage,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            throw new BadRequestHttpException('no_request');
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        if ($file === null) {
            throw new UnprocessableEntityHttpException('file_required');
        }

        if (!in_array($file->getMimeType(), UploadRules::ALLOWED_MIME_TYPES, true)) {
            throw new UnprocessableEntityHttpException('file_mime_type_invalid');
        }

        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            throw new UnprocessableEntityHttpException('file_too_large');
        }

        $suiviId = $uriVariables['suiviJournalierId'] ?? null;
        $suivi = $this->entityManager->find(SuiviJournalier::class, $suiviId);
        if (!$suivi instanceof SuiviJournalier) {
            throw new UnprocessableEntityHttpException('suivi_journalier.not_found');
        }

        $originalName = $file->getClientOriginalName();
        $extension    = $file->guessExtension() ?? 'bin';
        $filePath     = sprintf(
            'suivis-journaliers/%s/%s.%s',
            $suivi->getId(),
            bin2hex(random_bytes(8)),
            $extension,
        );

        $this->storage->write($filePath, file_get_contents($file->getPathname()));

        $document = new SuiviJournalierDocument();
        $document->setSuiviJournalier($suivi);
        $document->setFilePath($filePath);
        $document->setMimeType($file->getMimeType());
        $document->setNom($originalName);
        $document->setUploadedAt(new \DateTimeImmutable());
        $document->setCapturedAt(UploadRules::capturedAt($request));

        return $this->persistProcessor->process($document, $operation, $uriVariables, $context);
    }
}
