<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\ActivityPlanning\Service\PlanificationsDisponiblesProvider;
use App\Domain\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/planifications', name: 'planifications_disponibles', methods: ['GET'])]
#[IsGranted('ROLE_PRESTATAIRE')]
class PlanificationsDisponiblesController extends AbstractController
{
    public function __construct(private readonly PlanificationsDisponiblesProvider $provider) {}

    public function __invoke(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $member = $this->provider->forUser($user);

        return $this->json([
            'member'     => $member,
            'totalItems' => count($member),
        ]);
    }
}
