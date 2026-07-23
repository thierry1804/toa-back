<?php

declare(strict_types=1);

namespace App\Api\Controller;

use ApiPlatform\Metadata\GetCollection;
use App\Api\Provider\PermitTravailLogProvider;
use App\Domain\PermitTravail\Entity\PermitTravailLog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(
    '/api/permits-travail/{permitTravailId}/logs',
    name: 'permit_travail_logs',
    requirements: ['permitTravailId' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'],
    methods: ['GET'],
)]
#[IsGranted('PERMIT_TRAVAIL_LOGS')]
class PermitTravailLogsController extends AbstractController
{
    public function __construct(
        private readonly PermitTravailLogProvider $provider,
    ) {}

    public function __invoke(string $permitTravailId, Request $request): JsonResponse
    {
        $logs = $this->provider->provide(
            new GetCollection(uriTemplate: '/permits-travail/{permitTravailId}/logs'),
            ['permitTravailId' => $permitTravailId],
            ['request' => $request],
        );

        $member = array_map(
            static fn(PermitTravailLog $l): array => [
                'id'           => (string) $l->getId(),
                'action'       => $l->getAction()->value,
                'codeSite'     => $l->getCodeSite(),
                'typePermis'   => $l->getTypePermis()->value,
                'metadata'     => $l->getMetadata(),
                'createdAt'    => $l->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'declenchePar' => $l->getDeclenchePar() !== null ? [
                    'id'        => $l->getDeclenchePar()->getId(),
                    'email'     => $l->getDeclenchePar()->getEmail(),
                    'name'      => $l->getDeclenchePar()->getName(),
                    'firstname' => $l->getDeclenchePar()->getFirstname(),
                ] : null,
            ],
            $logs,
        );

        return $this->json([
            'member'     => $member,
            'totalItems' => count($member),
        ]);
    }
}
