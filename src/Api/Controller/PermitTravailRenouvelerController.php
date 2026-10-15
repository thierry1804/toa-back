<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Enum\ProcessusPermitTravail;
use App\Domain\PermitTravail\Enum\StatutPermitTravail;
use App\Domain\PermitTravail\Enum\TypePermitTravail;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * R-24 : renouvellement d'un permis Hauteur ou Électrique. Le renouvellement est un nouveau
 * permis (brouillon) lié à l'origine ; il reprend la référence d'origine suffixée R1, R2…
 */
#[Route(
    '/api/permits-travail/{permitId}/renouveler',
    name: 'permit_travail_renouveler',
    requirements: ['permitId' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'],
    methods: ['POST'],
)]
class PermitTravailRenouvelerController extends AbstractController
{
    private const STATUTS_RENOUVELABLES = [
        StatutPermitTravail::VALIDE_HSE,
        StatutPermitTravail::EN_COURS,
        StatutPermitTravail::VALIDE,
    ];

    private const STATUTS_EN_COURS = [StatutPermitTravail::BROUILLON, StatutPermitTravail::SOUMIS];

    public function __construct(private readonly EntityManagerInterface $entityManager) {}

    public function __invoke(string $permitId): JsonResponse
    {
        $origine = $this->entityManager->find(PermitTravail::class, $permitId);
        if (!$origine instanceof PermitTravail) {
            throw $this->createNotFoundException('permit_travail.not_found');
        }

        $this->denyAccessUnlessGranted('PERMIT_TRAVAIL_VIEW', $origine);
        // Création réservée au seul rôle Agent de terrain (R-14).
        $this->denyAccessUnlessGranted('PERMIT_TRAVAIL_CREATE');

        if (!in_array($origine->getType(), [TypePermitTravail::HAUTEUR, TypePermitTravail::ELECTRIQUE], true)) {
            throw new UnprocessableEntityHttpException('permit_travail.renouvellement_non_applicable');
        }

        if (!in_array($origine->getStatut(), self::STATUTS_RENOUVELABLES, true)) {
            throw new UnprocessableEntityHttpException('permit_travail.renouvellement_statut_invalide');
        }

        $enCours = $this->entityManager->getRepository(PermitTravail::class)->findOneBy([
            'permisOrigine' => $origine,
            'statut'        => self::STATUTS_EN_COURS,
        ]);
        if ($enCours !== null) {
            throw new UnprocessableEntityHttpException('permit_travail.renouvellement_en_cours');
        }

        /** @var User $user */
        $user = $this->getUser();

        $rang = ($origine->getNumeroRenouvellement() ?? 0) + 1;

        $renouvellement = new PermitTravail();
        $renouvellement->setReference(sprintf('%s-R%d', $origine->getReferenceOrigine(), $rang));
        $renouvellement->setNumeroRenouvellement($rang);
        $renouvellement->setPermisOrigine($origine);
        $renouvellement->setProcessus(ProcessusPermitTravail::RENOUVELLEMENT);
        $renouvellement->setType($origine->getType());
        $renouvellement->setPlanPrevention($origine->getPlanPrevention());
        $renouvellement->setCodeSite((string) $origine->getCodeSite());
        $renouvellement->setDescriptionTravaux($origine->getDescriptionTravaux());
        $renouvellement->setStatut(StatutPermitTravail::BROUILLON);
        $renouvellement->setCreatedBy($user);

        $this->entityManager->persist($renouvellement);
        $this->entityManager->flush();

        return new JsonResponse(
            [
                'id'        => $renouvellement->getId()?->toRfc4122(),
                'reference' => $renouvellement->getReference(),
            ],
            Response::HTTP_CREATED,
        );
    }
}
