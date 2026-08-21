<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\Referentiel\Repository\CategorieRisqueRepository;
use App\Security\Voter\CategorieRisqueVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/referentiel/categories-risque-list', name: 'referentiel_categorie_risque_list', methods: ['GET'])]
#[IsGranted(CategorieRisqueVoter::VIEW)]
class CategorieRisqueListController extends AbstractController
{
    private const PAGE_SIZE = 20;
    private const MAX_LIMIT = 1000;

    public function __construct(
        private readonly CategorieRisqueRepository $categorieRisqueRepository,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $page   = max(1, (int) $request->query->get('page', 1));
        $limit  = min(self::MAX_LIMIT, max(1, (int) $request->query->get('limit', self::PAGE_SIZE)));
        $search = trim((string) $request->query->get('search', ''));
        $offset = ($page - 1) * $limit;

        $qb = $this->categorieRisqueRepository->createQueryBuilder('c')
            ->orderBy('c.nom', 'ASC');

        if ($search !== '') {
            $qb->andWhere('LOWER(c.nom) LIKE LOWER(:search)')
               ->setParameter('search', '%' . $search . '%');
        }

        $totalQb = clone $qb;
        $total   = (int) $totalQb->select('COUNT(c.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        $items = $qb->select('c')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $data = array_map(static fn ($cat): array => [
            'id'         => (string) $cat->getId(),
            'nom'        => $cat->getNom(),
            'parentId'   => $cat->getParent()?->getId() !== null ? (string) $cat->getParent()->getId() : null,
            'parentNom'  => $cat->getParent()?->getNom(),
            'createdAt'  => $cat->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt'  => $cat->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ], $items);

        return $this->json([
            'member'     => $data,
            'totalItems' => $total,
        ]);
    }
}
