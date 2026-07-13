<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Menu\Service\PermissionChecker;
use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Entity\PermitTravailDocument;
use App\Domain\PermitTravail\Enum\StatutPermitTravail;
use App\Domain\PermitTravail\Enum\TypeDocumentPermitTravail;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class PermitTravailDocumentUploadProcessor implements ProcessorInterface
{
    private const ALLOWED_MIME_TYPES = ['application/pdf', 'image/jpeg'];
    private const MAX_SIZE_BYTES     = 10 * 1024 * 1024; // 10 MB

    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        #[Autowire('@default.storage')]
        private readonly FilesystemOperator $storage,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly PermissionChecker $permissionChecker,
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

        $typeValue = $request->request->get('type');
        if ($typeValue === null) {
            throw new UnprocessableEntityHttpException('type_required');
        }

        $type = TypeDocumentPermitTravail::tryFrom($typeValue);
        if ($type === null) {
            $validValues = implode(', ', array_column(TypeDocumentPermitTravail::cases(), 'value'));
            throw new UnprocessableEntityHttpException(sprintf('type_invalid: %s', $validValues));
        }

        if (!in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
            throw new UnprocessableEntityHttpException('file_mime_type_invalid');
        }

        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            throw new UnprocessableEntityHttpException('file_too_large');
        }

        $permitId = $uriVariables['permitTravailId'] ?? null;
        $permit = $this->entityManager->find(PermitTravail::class, $permitId);
        if (!$permit instanceof PermitTravail) {
            throw new UnprocessableEntityHttpException('permit_travail.not_found');
        }

        if ($permit->getStatut() !== StatutPermitTravail::BROUILLON) {
            throw new AccessDeniedException('permit_travail.statut_not_brouillon');
        }

        $currentUser = $this->tokenStorage->getToken()?->getUser();
        if ($currentUser instanceof User) {
            $roleActions = $this->permissionChecker->getRoleActions($currentUser->getRoles(), 'permit_travail.edit');
            $canBypass = array_filter($roleActions, fn($ra) => $ra->isBypassOwnership());
            if (empty($canBypass)) {
                $permitOwner = $permit->getCreatedBy();
                if ($permitOwner !== null && $permitOwner->getUserIdentifier() !== $currentUser->getUserIdentifier()) {
                    throw new AccessDeniedException('error.voter.access_denied');
                }
            }
        }

        $extension = $file->guessExtension() ?? 'bin';
        $filePath  = sprintf(
            'permits-travail/%s/%s/%s.%s',
            $permit->getId(),
            $type->value,
            bin2hex(random_bytes(8)),
            $extension,
        );

        // Remove existing document of same type (file + DB record)
        foreach ($permit->getDocuments() as $existing) {
            if ($existing->getType() === $type) {
                try {
                    $this->storage->delete($existing->getFilePath());
                } catch (\Throwable) {
                }
                $this->entityManager->remove($existing);
            }
        }
        $this->entityManager->flush();

        $this->storage->write($filePath, file_get_contents($file->getPathname()));

        $document = new PermitTravailDocument();
        $document->setPermitTravail($permit);
        $document->setType($type);
        $document->setFilePath($filePath);
        $document->setMimeType($file->getMimeType() ?? '');
        $document->setUploadedAt(new \DateTimeImmutable());

        return $this->persistProcessor->process($document, $operation, $uriVariables, $context);
    }
}
