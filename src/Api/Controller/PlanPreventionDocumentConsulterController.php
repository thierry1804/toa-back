<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\PlanPrevention\Entity\DocumentPrevention;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/api/plans-prevention/{planId}/documents/{documentId}/consulter',
    name: 'plan_prevention_document_consulter',
    requirements: [
        'planId' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}',
        'documentId' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}',
    ],
    methods: ['POST'],
)]
class PlanPreventionDocumentConsulterController extends AbstractController
{
    private const REVIEWER_ROLES = ['ROLE_CHEF_PROJET', 'ROLE_HSE', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'];

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function __invoke(string $planId, string $documentId): JsonResponse
    {
        $plan = $this->entityManager->find(PlanPrevention::class, $planId);
        if (!$plan instanceof PlanPrevention) {
            throw $this->createNotFoundException('plan_prevention.not_found');
        }

        $this->denyAccessUnlessGranted('PLAN_PREVENTION_VIEW', $plan);

        $document = null;
        foreach ($plan->getDocuments() as $candidate) {
            if ((string) $candidate->getId() === $documentId) {
                $document = $candidate;
                break;
            }
        }
        if (!$document instanceof DocumentPrevention) {
            throw $this->createNotFoundException('document_prevention.not_found');
        }

        $user = $this->getUser();
        if ($user instanceof User && $this->isReviewer()) {
            $document->markConsulted($user, new \DateTimeImmutable());
            $this->entityManager->flush();
        }

        return new JsonResponse([
            'documentId' => (string) $document->getId(),
            'consultedAt' => $document->getConsultedAt()?->format(\DateTimeInterface::ATOM),
            'tousDocumentsConsultes' => $plan->isTousDocumentsConsultes(),
        ]);
    }

    private function isReviewer(): bool
    {
        foreach (self::REVIEWER_ROLES as $role) {
            if ($this->isGranted($role)) {
                return true;
            }
        }

        return false;
    }
}
