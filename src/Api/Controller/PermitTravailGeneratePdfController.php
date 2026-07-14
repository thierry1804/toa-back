<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Entity\PermitTravailPdf;
use App\Domain\PermitTravail\Enum\StatutPermitTravail;
use App\Domain\PermitTravail\Enum\StatutPermitTravailPdf;
use App\Domain\PermitTravail\Message\GeneratePermitTravailPdfMessage;
use App\Domain\PermitTravail\Repository\PermitTravailPdfRepository;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

class PermitTravailGeneratePdfController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PermitTravailPdfRepository $pdfRepository,
        private readonly MessageBusInterface $messageBus,
    ) {}

    #[Route(
        '/api/permits-travail/{permitId}/generate-pdf',
        name: 'permit_travail_generate_pdf',
        methods: ['POST'],
    )]
    public function __invoke(string $permitId): JsonResponse
    {
        $permit = $this->entityManager->find(PermitTravail::class, $permitId);
        if (!$permit instanceof PermitTravail) {
            throw $this->createNotFoundException('permit_travail.not_found');
        }

        $this->denyAccessUnlessGranted('PERMIT_TRAVAIL_GENERATE_PDF', $permit);

        if ($permit->getStatut() !== StatutPermitTravail::VALIDE_HSE) {
            throw new UnprocessableEntityHttpException(
                'PDF disponible uniquement pour un permis validé par HSE',
            );
        }

        /** @var User $user */
        $user = $this->getUser();

        $existing = $this->pdfRepository->findByPermitTravailId($permitId);
        if ($existing !== null) {
            $existing->setStatut(StatutPermitTravailPdf::EN_COURS);
            $existing->setFilePath(null);
            $existing->setGenereAt(null);
            $existing->setTailleFichier(null);
            $jobId = Uuid::v4()->toRfc4122();
            $existing->setJobId($jobId);
            $this->entityManager->flush();
        } else {
            $jobId = Uuid::v4()->toRfc4122();
            $pdfRecord = new PermitTravailPdf();
            $pdfRecord->setPermitTravail($permit);
            $pdfRecord->setTypePermis($permit->getType()?->value);
            $pdfRecord->setGenerePar($user);
            $pdfRecord->setJobId($jobId);
            $pdfRecord->setStatut(StatutPermitTravailPdf::EN_COURS);
            $this->entityManager->persist($pdfRecord);
            $this->entityManager->flush();
        }

        $this->messageBus->dispatch(new GeneratePermitTravailPdfMessage(
            permitTravailId: (string) $permit->getId(),
            jobId: $jobId,
            requesterId: $user->getId(),
        ));

        return new JsonResponse(['jobId' => $jobId, 'statut' => 'EN_COURS'], Response::HTTP_ACCEPTED);
    }
}
