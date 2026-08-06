<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\Referentiel\Repository\InstallationEquipementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/referentiel/installations-equipements-all', name: 'referentiel_installation_equipement_all', methods: ['GET'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class InstallationEquipementAllController extends AbstractController
{
    public function __construct(
        private readonly InstallationEquipementRepository $repository,
    ) {}

    public function __invoke(): JsonResponse
    {
        $items = $this->repository->createQueryBuilder('ie')
            ->select('ie.id', 'ie.nom')
            ->where('ie.deletedAt IS NULL')
            ->orderBy('ie.nom', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $data = array_map(static fn (array $row): array => [
            'id'  => (string) $row['id'],
            'nom' => $row['nom'],
        ], $items);

        return $this->json(['member' => $data, 'totalItems' => count($data)]);
    }
}
