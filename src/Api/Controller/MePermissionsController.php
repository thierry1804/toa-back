<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\Role\Repository\RoleActionRepository;
use App\Domain\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class MePermissionsController extends AbstractController
{
    public function __construct(
        private readonly RoleActionRepository $roleActionRepository,
    ) {
    }

    #[Route('/api/me/permissions', name: 'me_permissions', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $permissions = $this->roleActionRepository->findActionKeysByRoles($user->getRoles());

        return $this->json([
            'permissions' => array_values(array_unique($permissions)),
        ]);
    }
}
