<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Entity\PermitTravailDocument;
use App\Domain\PermitTravail\Enum\TypeDocumentPermitTravail;
use App\Domain\PermitTravail\Service\PermitDocumentAccessGuard;
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
    '/api/permits-travail/{permitId}/documents/non-applicable',
    name: 'permit_travail_document_non_applicable',
    requirements: ['permitId' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'],
    methods: ['POST'],
)]
class PermitTravailDocumentNonApplicableController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PermitDocumentAccessGuard $accessGuard,
        #[Autowire('@default.storage')]
        private readonly FilesystemOperator $storage,
    ) {
    }

    public function __invoke(string $permitId, Request $request): Response
    {
        $permit = $this->entityManager->find(PermitTravail::class, $permitId);
        if (!$permit instanceof PermitTravail) {
            throw $this->createNotFoundException('permit_travail.not_found');
        }

        $this->denyAccessUnlessGranted('PERMIT_TRAVAIL_EDIT');

        $body = json_decode($request->getContent(), true);
        if (!is_array($body) || !isset($body['type']) || !is_bool($body['nonApplicable'] ?? null)) {
            throw new UnprocessableEntityHttpException('type_and_non_applicable_required');
        }

        $type = TypeDocumentPermitTravail::tryFrom((string) $body['type']);
        if ($type === null) {
            throw new UnprocessableEntityHttpException('type_invalid');
        }

        $this->accessGuard->assertCanModify($permit, $type);

        $existing = array_filter(
            $permit->getDocuments()->toArray(),
            static fn (PermitTravailDocument $doc) => $doc->getType() === $type,
        );

        if ($body['nonApplicable'] === false) {
            foreach ($existing as $document) {
                if ($document->isNonApplicable()) {
                    $permit->getDocuments()->removeElement($document);
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
            $permit->getDocuments()->removeElement($document);
            $this->entityManager->remove($document);
        }

        $now = new \DateTimeImmutable();
        $marker = new PermitTravailDocument();
        $marker->setPermitTravail($permit);
        $marker->setType($type);
        $marker->setFilePath('');
        $marker->setMimeType('');
        $marker->setUploadedAt($now);
        $marker->setCapturedAt($now);
        $marker->setNonApplicable(true);
        $permit->getDocuments()->add($marker);

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
        ], Response::HTTP_CREATED);
    }
}
