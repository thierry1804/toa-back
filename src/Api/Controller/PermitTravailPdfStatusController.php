<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Repository\PermitTravailPdfRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class PermitTravailPdfStatusController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PermitTravailPdfRepository $pdfRepository,
    ) {}

    #[Route(
        '/api/permits-travail/{permitId}/pdf/status',
        name: 'permit_travail_pdf_status',
        methods: ['GET'],
    )]
    public function __invoke(string $permitId): JsonResponse
    {
        $permit = $this->entityManager->find(PermitTravail::class, $permitId);
        if (!$permit instanceof PermitTravail) {
            throw $this->createNotFoundException('permit_travail.not_found');
        }

        $this->denyAccessUnlessGranted('PERMIT_TRAVAIL_VIEW', $permit);

        $pdfRecord = $this->pdfRepository->findByPermitTravailId($permitId);
        if ($pdfRecord === null) {
            return new JsonResponse(['statut' => null, 'jobId' => null]);
        }

        return new JsonResponse([
            'statut'        => $pdfRecord->getStatut()->value,
            'jobId'         => $pdfRecord->getJobId(),
            'genereAt'      => $pdfRecord->getGenereAt()?->format(\DateTimeInterface::ATOM),
            'tailleFichier' => $pdfRecord->getTailleFichier(),
        ]);
    }
}
