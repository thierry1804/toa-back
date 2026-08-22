<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Menu\Service\PermissionChecker;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class SignatureUploadProcessor implements ProcessorInterface
{
    private const ALLOWED_MIME_TYPES = ['image/webp', 'image/png'];
    private const MAX_SIZE_BYTES     = 2 * 1024 * 1024; // 2 MB

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

        $userId = $uriVariables['id'] ?? null;
        $targetUser = $this->entityManager->find(User::class, $userId);
        if (!$targetUser instanceof User) {
            throw new NotFoundHttpException('user.not_found');
        }

        $currentUser = $this->tokenStorage->getToken()?->getUser();
        if (!$currentUser instanceof User) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        $roleActions = $this->permissionChecker->getRoleActions($currentUser->getRoles(), 'user.upload_signature');
        $canBypass   = array_filter($roleActions, fn($ra) => $ra->isBypassOwnership());

        $isSelf = $currentUser->getId() === $targetUser->getId();
        // A prestataire scoped to an entreprise manages their own team (see
        // PrestataireUserScopeProcessor) — that includes setting up a
        // teammate's signature, not just their own.
        $canActForTeammate = $this->isPrestataireScoped($currentUser)
            && $this->usersShareEntreprise($currentUser, $targetUser);

        if (empty($canBypass) && !$isSelf && !$canActForTeammate) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        if ($file === null) {
            throw new UnprocessableEntityHttpException('file_required');
        }

        if (!in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
            throw new UnprocessableEntityHttpException('file_mime_type_invalid');
        }

        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            throw new UnprocessableEntityHttpException('file_too_large');
        }

        $extension = $file->guessExtension() ?? 'png';
        $filePath  = sprintf(
            'users-signatures/%s/signature_%s.%s',
            $targetUser->getId(),
            bin2hex(random_bytes(8)),
            $extension,
        );

        if ($targetUser->getSignaturePath() !== null) {
            try {
                $this->storage->delete($targetUser->getSignaturePath());
            } catch (\Throwable) {
            }
        }

        $this->storage->write($filePath, file_get_contents($file->getPathname()));

        $targetUser->setSignaturePath($filePath);
        $this->entityManager->flush();

        return $targetUser;
    }

    private function isPrestataireScoped(User $user): bool
    {
        $roles = $user->getRoles();

        return in_array('ROLE_PRESTATAIRE', $roles, true)
            && !in_array('ROLE_ADMIN', $roles, true)
            && !in_array('ROLE_SUPER_ADMIN', $roles, true);
    }

    private function usersShareEntreprise(User $a, User $b): bool
    {
        $entA = $a->getEntreprise();
        $entB = $b->getEntreprise();

        if ($entA === null || $entB === null || $entA->getId() === null || $entB->getId() === null) {
            return false;
        }

        return $entA->getId()->equals($entB->getId());
    }
}
