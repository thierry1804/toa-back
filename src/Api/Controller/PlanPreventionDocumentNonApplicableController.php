<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\PlanPrevention\Entity\DocumentPrevention;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Enum\TypeDocumentPrevention;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/api/plans-prevention/{planId}/documents/non-applicable',
    name: 'plan_prevention_document_non_applicable',
    requirements: ['planId' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'],
    methods: ['POST'],
)]
class PlanPreventionDocumentNonApplicableController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('@default.storage')]
        private readonly FilesystemOperator $storage,
    ) {
    }

    public function __invoke(string $planId, Request $request): Response
    {
        $plan = $this->entityManager->find(PlanPrevention::class, $planId);
        if (!$plan instanceof PlanPrevention) {
            throw $this->createNotFoundException('plan_prevention.not_found');
        }

        $this->denyAccessUnlessGranted('PLAN_PREVENTION_EDIT', $plan);

        $body = json_decode($request->getContent(), true);
        if (!is_array($body) || !isset($body['type']) || !is_bool($body['nonApplicable'] ?? null)) {
            throw new UnprocessableEntityHttpException('type_and_non_applicable_required');
        }

        $type = TypeDocumentPrevention::tryFrom((string) $body['type']);
        if ($type === null) {
            throw new UnprocessableEntityHttpException('type_invalid');
        }

        $existing = array_filter(
            $plan->getDocuments()->toArray(),
            static fn (DocumentPrevention $doc) => $doc->getType() === $type,
        );

        if ($body['nonApplicable'] === false) {
            foreach ($existing as $document) {
                if ($document->isNonApplicable()) {
                    $plan->getDocuments()->removeElement($document);
                    $this->entityManager->remove($document);
                }
            }
            $this->entityManager->flush();

            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        foreach ($existing as $document) {
            if (!$document->isNonApplicable() && $document->getFilePath() !== null && $document->getFilePath() !== '') {
                try {
                    $this->storage->delete($document->getFilePath());
                } catch (\Throwable) {
                    // Ignore missing file in storage
                }
            }
            $plan->getDocuments()->removeElement($document);
            $this->entityManager->remove($document);
        }

        $now = new \DateTimeImmutable();
        $marker = new DocumentPrevention();
        $marker->setPlanPrevention($plan);
        $marker->setType($type);
        $marker->setFilePath('');
        $marker->setMimeType('');
        $marker->setUploadedAt($now);
        $marker->setCapturedAt($now);
        $marker->setNonApplicable(true);
        $plan->addDocument($marker);

        $this->entityManager->persist($marker);
        $this->entityManager->flush();

        return new JsonResponse([
            'id' => (string) $marker->getId(),
            'type' => $type->value,
            'filePath' => '',
            'mimeType' => '',
            'uploadedAt' => $now->format(\DateTimeInterface::ATOM),
            'capturedAt' => $now->format(\DateTimeInterface::ATOM),
            'nonApplicable' => true,
            'consultedAt' => null,
        ], Response::HTTP_CREATED);
    }
}
