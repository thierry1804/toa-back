<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Repository\CloturePermRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/api/permits-travail/{permitId}/cloture',
    name: 'permit_travail_cloture_detail',
    requirements: ['permitId' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'],
    methods: ['GET'],
)]
class PermitTravailClotureDetailController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CloturePermRepository $clotureRepository,
    ) {}

    public function __invoke(string $permitId): JsonResponse
    {
        $permit = $this->entityManager->find(PermitTravail::class, $permitId);
        if (!$permit instanceof PermitTravail) {
            throw new NotFoundHttpException('permit_travail.not_found');
        }

        $this->denyAccessUnlessGranted('PERMIT_TRAVAIL_VIEW', $permit);

        $cloture = $this->clotureRepository->findByPermitTravailId($permitId);
        if ($cloture === null) {
            throw new NotFoundHttpException('cloture_permit.not_found');
        }

        return new JsonResponse([
            'id'                  => $cloture->getId()?->toRfc4122(),
            'typeCloture'         => $cloture->getTypeCloture()->value,
            'dateClotureEffective' => $cloture->getDateClotureEffective()?->format(\DateTimeInterface::ATOM),
            'commentaire'         => $cloture->getCommentaire(),
            'accordClient'        => $cloture->isAccordClient(),
            'accordClientAt'      => $cloture->getAccordClientAt()?->format(\DateTimeInterface::ATOM),
            'clotureBy'           => $cloture->getClotureBy() !== null ? [
                'id'        => $cloture->getClotureBy()->getId(),
                'name'      => $cloture->getClotureBy()->getName(),
                'firstname' => $cloture->getClotureBy()->getFirstname(),
            ] : null,
            'createdAt'           => $cloture->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ]);
    }
}
