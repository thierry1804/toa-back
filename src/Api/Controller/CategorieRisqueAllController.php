<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\Referentiel\Repository\CategorieRisqueRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/referentiel/categories-risque-all', name: 'referentiel_categorie_risque_all', methods: ['GET'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class CategorieRisqueAllController extends AbstractController
{
    public function __construct(
        private readonly CategorieRisqueRepository $categorieRisqueRepository,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $items = $this->categorieRisqueRepository->createQueryBuilder('c')
            ->leftJoin('c.parent', 'p')
            ->select('c.id', 'c.nom', 'c.typePermis', 'c.createdAt', 'c.updatedAt', 'IDENTITY(c.parent) AS parentId', 'p.nom AS parentNom')
            ->orderBy('c.nom', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $data = array_map(static fn (array $row): array => [
            'id'         => (string) $row['id'],
            'nom'        => $row['nom'],
            'typePermis' => $row['typePermis'],
            'parentId'   => $row['parentId'] !== null ? (string) $row['parentId'] : null,
            'parentNom'  => $row['parentNom'],
            'createdAt'  => $row['createdAt']->format(\DateTimeInterface::ATOM),
            'updatedAt'  => $row['updatedAt']?->format(\DateTimeInterface::ATOM),
        ], $items);

        return $this->json(['member' => $data, 'totalItems' => count($data)]);
    }
}
