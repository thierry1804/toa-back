<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Enum\TypeDocumentPermitTravail;
use App\Domain\PermitTravail\Service\PermitDocumentRequirementResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/api/permits-travail/{permitId}/documents-requis',
    name: 'permit_travail_documents_requis',
    requirements: ['permitId' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'],
    methods: ['GET'],
)]
class PermitTravailDocumentsRequisController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PermitDocumentRequirementResolver $resolver,
    ) {
    }

    public function __invoke(string $permitId): JsonResponse
    {
        $permit = $this->entityManager->find(PermitTravail::class, $permitId);
        if (!$permit instanceof PermitTravail) {
            throw $this->createNotFoundException('permit_travail.not_found');
        }

        $this->denyAccessUnlessGranted('PERMIT_TRAVAIL_VIEW', $permit);

        $values = static fn (array $types): array => array_map(
            static fn (TypeDocumentPermitTravail $type): string => $type->value,
            $types,
        );

        return new JsonResponse([
            'soumission' => $values($this->resolver->getRequiredDocumentTypes($permit)),
            'cloture' => $values($this->resolver->getRequiredClotureDocumentTypes($permit)),
            'apnApi' => $this->resolver->isApnApiSite($permit),
        ]);
    }
}
