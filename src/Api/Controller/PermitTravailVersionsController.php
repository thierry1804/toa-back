<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Repository\VersionPermitTravailRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

class PermitTravailVersionsController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly VersionPermitTravailRepository $versionRepository,
    ) {
    }

    #[Route('/api/permits-travail/{permitId}/versions', name: 'permit_travail_versions', methods: ['GET'])]
    public function __invoke(string $permitId): JsonResponse
    {
        $permit = $this->entityManager->find(PermitTravail::class, Uuid::fromString($permitId));

        if (!$permit instanceof PermitTravail) {
            throw new NotFoundHttpException('permit_travail.not_found');
        }

        $this->denyAccessUnlessGranted('PERMIT_TRAVAIL_VIEW', $permit);

        $versions = $this->versionRepository->findByPermit($permit);

        return $this->json(array_map(
            static fn($v) => [
                'id'               => $v->getId()?->toRfc4122(),
                'numeroVersion'    => $v->getNumeroVersion(),
                'motifResoumission' => $v->getMotifResoumission(),
                'createdAt'        => $v->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                'createdBy'        => [
                    'id'   => $v->getCreatedBy()?->getId(),
                    'name' => trim(sprintf('%s %s', $v->getCreatedBy()?->getFirstname() ?? '', $v->getCreatedBy()?->getName() ?? '')),
                ],
            ],
            $versions,
        ));
    }
}
