<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Volontairement séparé de PATCH /api/users/{id} : cette route n'expose que
 * le mot de passe, jamais `roles`/`entreprise`/`isActive` (présents dans le
 * groupe user:write) — n'importe quel utilisateur connecté peut donc s'en
 * servir sans ouvrir de chemin d'auto-élévation de privilège.
 */
class MePasswordController extends AbstractController
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/me/password', name: 'me_password', methods: ['PATCH'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            throw new BadRequestHttpException('invalid_json');
        }

        $currentPassword = (string) ($data['currentPassword'] ?? '');
        $newPassword     = (string) ($data['newPassword'] ?? '');

        if ($currentPassword === '' || $newPassword === '') {
            throw new UnprocessableEntityHttpException('current_and_new_password_required');
        }

        if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            throw new UnprocessableEntityHttpException('current_password_invalid');
        }

        if (mb_strlen($newPassword) < 8 || mb_strlen($newPassword) > 128) {
            throw new UnprocessableEntityHttpException('new_password_length_invalid');
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $this->entityManager->flush();

        return new JsonResponse(['status' => 'ok']);
    }
}
