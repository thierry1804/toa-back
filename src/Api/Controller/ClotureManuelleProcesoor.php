<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\PermitTravail\Entity\CloturePerm;
use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Entity\PvReceptionPdf;
use App\Domain\PermitTravail\Enum\StatutPermitTravail;
use App\Domain\PermitTravail\Enum\StatutPvReceptionPdf;
use App\Domain\PermitTravail\Enum\TypeCloturePerm;
use App\Domain\PermitTravail\Message\GeneratePvReceptionMessage;
use App\Domain\PermitTravail\Repository\PvReceptionPdfRepository;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/api/permits-travail/{permitId}/cloturer',
    name: 'permit_travail_cloturer',
    requirements: ['permitId' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'],
    methods: ['POST'],
)]
class ClotureManuelleProcesoor extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PvReceptionPdfRepository $pvRepository,
        private readonly MessageBusInterface $messageBus,
    ) {}

    public function __invoke(string $permitId, Request $request): JsonResponse
    {
        $permit = $this->entityManager->find(PermitTravail::class, $permitId);
        if (!$permit instanceof PermitTravail) {
            throw $this->createNotFoundException('permit_travail.not_found');
        }

        $this->denyAccessUnlessGranted('PERMIT_TRAVAIL_CLOTURER', $permit);

        if ($permit->getStatut() === StatutPermitTravail::CLOTURE) {
            throw new UnprocessableEntityHttpException('permit_travail.deja_cloture');
        }

        $body        = json_decode($request->getContent(), true) ?? [];
        $commentaire = isset($body['commentaire']) ? (string) $body['commentaire'] : null;
        $accordClient = (bool) ($body['accordClient'] ?? false);

        /** @var User $user */
        $user = $this->getUser();
        $now  = new \DateTimeImmutable();

        $cloture = new CloturePerm();
        $cloture->setPermitTravail($permit);
        $cloture->setTypeCloture(TypeCloturePerm::MANUELLE);
        $cloture->setDateClotureEffective($now);
        $cloture->setCommentaire($commentaire);
        $cloture->setAccordClient($accordClient);
        if ($accordClient) {
            $cloture->setAccordClientAt($now);
        }
        $cloture->setClotureBy($user);

        $permit->setStatut(StatutPermitTravail::CLOTURE);

        $this->entityManager->persist($cloture);

        $existing = $this->pvRepository->findByPermitTravailId($permitId);
        if ($existing !== null) {
            $existing->setStatut(StatutPvReceptionPdf::EN_COURS);
            $existing->setFilePath(null);
            $existing->setGenereAt(null);
            $existing->setTailleFichier(null);
            $jobId = Uuid::v4()->toRfc4122();
            $existing->setJobId($jobId);
        } else {
            $jobId   = Uuid::v4()->toRfc4122();
            $pvRecord = new PvReceptionPdf();
            $pvRecord->setPermitTravail($permit);
            $pvRecord->setGenerePar($user);
            $pvRecord->setJobId($jobId);
            $pvRecord->setStatut(StatutPvReceptionPdf::EN_COURS);
            $this->entityManager->persist($pvRecord);
        }

        $this->entityManager->flush();

        $this->messageBus->dispatch(new GeneratePvReceptionMessage(
            permitTravailId: (string) $permit->getId(),
            jobId: $jobId,
            requesterId: $user->getId(),
        ));

        return new JsonResponse(
            ['jobId' => $jobId, 'statut' => 'EN_COURS', 'message' => 'permit_travail.cloture_initiated'],
            Response::HTTP_ACCEPTED,
        );
    }
}
