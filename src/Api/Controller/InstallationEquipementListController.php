<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\Referentiel\Repository\InstallationEquipementRepository;
use App\Security\Voter\InstallationEquipementVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/referentiel/installations-equipements-list', name: 'referentiel_installation_equipement_list', methods: ['GET'])]
#[IsGranted(InstallationEquipementVoter::VIEW)]
class InstallationEquipementListController extends AbstractController
{
    private const PAGE_SIZE = 20;
    private const MAX_LIMIT = 1000;

    public function __construct(
        private readonly InstallationEquipementRepository $repository,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $page   = max(1, (int) $request->query->get('page', 1));
        $limit  = min(self::MAX_LIMIT, max(1, (int) $request->query->get('limit', self::PAGE_SIZE)));
        $search = trim((string) $request->query->get('search', ''));
        $offset = ($page - 1) * $limit;

        $qb = $this->repository->createQueryBuilder('ie')
            ->where('ie.deletedAt IS NULL')
            ->orderBy('ie.nom', 'ASC');

        if ($search !== '') {
            $qb->andWhere('LOWER(ie.nom) LIKE LOWER(:search)')
               ->setParameter('search', '%' . $search . '%');
        }

        $totalQb = clone $qb;
        $total   = (int) $totalQb->select('COUNT(ie.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        $items = $qb->select('ie')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $data = array_map(static fn ($item): array => [
            'id'        => (string) $item->getId(),
            'nom'       => $item->getNom(),
            'createdAt' => $item->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $item->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
            'deletedAt' => $item->getDeletedAt()?->format(\DateTimeInterface::ATOM),
        ], $items);

        return $this->json([
            'member'     => $data,
            'totalItems' => $total,
        ]);
    }
}
