<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Repository\PvReceptionPdfRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/api/permits-travail/{permitId}/pv/status',
    name: 'permit_travail_pv_status',
    requirements: ['permitId' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'],
    methods: ['GET'],
)]
class PvReceptionPdfStatusController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PvReceptionPdfRepository $pvRepository,
    ) {}

    public function __invoke(string $permitId): JsonResponse
    {
        $permit = $this->entityManager->find(PermitTravail::class, $permitId);
        if (!$permit instanceof PermitTravail) {
            throw new NotFoundHttpException('permit_travail.not_found');
        }

        $this->denyAccessUnlessGranted('PERMIT_TRAVAIL_VIEW', $permit);

        $pvRecord = $this->pvRepository->findByPermitTravailId($permitId);
        if ($pvRecord === null) {
            return new JsonResponse(['statut' => null, 'genereAt' => null, 'tailleFichier' => null]);
        }

        return new JsonResponse([
            'statut'        => $pvRecord->getStatut()->value,
            'genereAt'      => $pvRecord->getGenereAt()?->format(\DateTimeInterface::ATOM),
            'tailleFichier' => $pvRecord->getTailleFichier(),
        ]);
    }
}
