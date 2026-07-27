<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Repository\DecisionCdpPvReceptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/api/permits-travail/{permitId}/pv/decision',
    name: 'permit_travail_pv_decision',
    requirements: ['permitId' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'],
    methods: ['GET'],
)]
class PermitTravailPvDecisionController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DecisionCdpPvReceptionRepository $decisionRepository,
    ) {}

    public function __invoke(string $permitId): JsonResponse
    {
        $permit = $this->entityManager->find(PermitTravail::class, $permitId);
        if (!$permit instanceof PermitTravail) {
            throw $this->createNotFoundException('permit_travail.not_found');
        }

        $this->denyAccessUnlessGranted('PERMIT_TRAVAIL_VIEW', $permit);

        $decisions = $this->decisionRepository->findByPermitTravailId($permitId);

        $data = array_map(static fn($d) => [
            'id'                   => $d->getId()?->toRfc4122(),
            'decision'             => $d->getDecision()?->value,
            'decidePar'            => $d->getDecidePar() !== null ? [
                'id'        => $d->getDecidePar()->getId(),
                'name'      => $d->getDecidePar()->getName(),
                'firstname' => $d->getDecidePar()->getFirstname(),
            ] : null,
            'commentaire'          => $d->getCommentaire(),
            'signatureElectronique' => $d->getSignatureElectronique(),
            'decidedAt'            => $d->getDecidedAt()?->format(\DateTimeInterface::ATOM),
        ], $decisions);

        return new JsonResponse($data);
    }
}
