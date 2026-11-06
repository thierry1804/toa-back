<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Entity\PermitTravailDocument;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/api/permits-travail/{permitId}/documents/{documentId}/consulter',
    name: 'permit_travail_document_consulter',
    requirements: [
        'permitId' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}',
        'documentId' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}',
    ],
    methods: ['POST'],
)]
class PermitTravailDocumentConsulterController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function __invoke(string $permitId, string $documentId): JsonResponse
    {
        $permit = $this->entityManager->find(PermitTravail::class, $permitId);
        if (!$permit instanceof PermitTravail) {
            throw $this->createNotFoundException('permit_travail.not_found');
        }

        $this->denyAccessUnlessGranted('PERMIT_TRAVAIL_VIEW', $permit);

        $document = null;
        foreach ($permit->getDocuments() as $candidate) {
            if ((string) $candidate->getId() === $documentId) {
                $document = $candidate;
                break;
            }
        }
        if (!$document instanceof PermitTravailDocument) {
            throw $this->createNotFoundException('permit_travail_document.not_found');
        }

        $user = $this->getUser();
        if ($user instanceof User && $this->isGranted('ROLE_HSE')) {
            $document->markConsulted($user, new \DateTimeImmutable());
            $this->entityManager->flush();
        }

        return new JsonResponse([
            'documentId' => (string) $document->getId(),
            'consultedAt' => $document->getConsultedAt()?->format(\DateTimeInterface::ATOM),
            'tousDocumentsConsultes' => $permit->isTousDocumentsConsultes(),
        ]);
    }
}
