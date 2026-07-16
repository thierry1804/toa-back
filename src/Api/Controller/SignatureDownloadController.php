<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToReadFile;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class SignatureDownloadController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('@default.storage')]
        private readonly FilesystemOperator $storage,
    ) {}

    #[Route(
        '/api/users/{id}/signature/download',
        name: 'user_signature_download',
        methods: ['GET'],
    )]
    public function __invoke(int $id): StreamedResponse
    {
        $targetUser = $this->entityManager->find(User::class, $id);
        if (!$targetUser instanceof User) {
            throw new NotFoundHttpException('user.not_found');
        }

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($currentUser->getId() !== $targetUser->getId()) {
            $this->denyAccessUnlessGranted('USER_UPLOAD_SIGNATURE');
        }

        $filePath = $targetUser->getSignaturePath();
        if ($filePath === null) {
            throw new NotFoundHttpException('user.signature_not_found');
        }

        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $mimeType  = $extension === 'webp' ? 'image/webp' : 'image/png';

        try {
            $stream = $this->storage->readStream($filePath);
        } catch (UnableToReadFile) {
            throw new NotFoundHttpException('user.signature_file_not_found');
        }

        return new StreamedResponse(static function () use ($stream): void {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type'        => $mimeType,
            'Content-Disposition' => 'inline; filename="signature.' . $extension . '"',
            'Cache-Control'       => 'private, max-age=900',
        ]);
    }
}
