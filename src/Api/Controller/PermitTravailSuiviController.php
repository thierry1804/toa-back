<?php

declare(strict_types=1);

namespace App\Api\Controller;

use ApiPlatform\Metadata\Get;
use App\Api\Provider\PermitTravailSuiviProvider;
use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Entity\PermitTravailLog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/permits-travail/suivi', name: 'permit_travail_suivi', methods: ['GET'])]
#[IsGranted('PERMIT_TRAVAIL_SUIVI')]
class PermitTravailSuiviController extends AbstractController
{
    public function __construct(
        private readonly PermitTravailSuiviProvider $provider,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $output = $this->provider->provide(
            new Get(uriTemplate: '/permits-travail/suivi'),
            [],
            ['request' => $request],
        );

        return $this->json([
            'totalPermis'   => $output->totalPermis,
            'parStatut'     => $output->parStatut,
            'parType'       => $output->parType,
            'parSite'       => $output->parSite,
            'expirantSous7j' => array_map(
                static fn(PermitTravail $p): array => [
                    'id'             => (string) $p->getId(),
                    'reference'      => $p->getReference(),
                    'typePermis'     => $p->getType()?->value,
                    'codeSite'       => $p->getCodeSite(),
                    'statut'         => $p->getStatut()->value,
                    'dateFinPrevue'  => $p->getDateFinPrevue()?->format(\DateTimeInterface::ATOM),
                ],
                $output->expirantSous7j,
            ),
            'logsRecents' => array_map(
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
                $output->logsRecents,
            ),
        ]);
    }
}
