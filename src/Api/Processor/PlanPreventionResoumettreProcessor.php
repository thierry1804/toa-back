<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Entity\VersionPlanPrevention;
use App\Domain\PlanPrevention\Enum\DecisionHse;
use App\Domain\PlanPrevention\Enum\StatutPlanPrevention;
use App\Domain\PlanPrevention\Enum\TypeDocumentPrevention;
use App\Domain\PlanPrevention\Repository\VersionPlanPreventionRepository;
use App\Domain\PlanPrevention\Service\PlanPreventionNotificationService;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class PlanPreventionResoumettreProcessor implements ProcessorInterface
{
    private const REQUIRED_DOCUMENT_TYPES = [
        TypeDocumentPrevention::PLAN_URGENCE,
        TypeDocumentPrevention::FDS,
        TypeDocumentPrevention::LISTE_INTERVENANTS,
        TypeDocumentPrevention::ATTESTATION_HSE,
        TypeDocumentPrevention::FICHE_CONFORMITE,
    ];

    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly EntityManagerInterface $entityManager,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly VersionPlanPreventionRepository $versionRepository,
        private readonly PlanPreventionNotificationService $notificationService,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $plan = $data instanceof PlanPrevention
            ? $data
            : $this->entityManager->find(PlanPrevention::class, $uriVariables['id'] ?? null);

        if (!$plan instanceof PlanPrevention) {
            throw new UnprocessableEntityHttpException('plan_prevention.not_found');
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User) {
            throw new UnprocessableEntityHttpException('plan_prevention.user_not_found');
        }

        if ($plan->getStatut() !== StatutPlanPrevention::BROUILLON) {
            throw new UnprocessableEntityHttpException('plan_prevention.statut_not_brouillon');
        }

        if ($plan->getCreatedBy()?->getUserIdentifier() !== $user->getUserIdentifier()) {
            throw new UnprocessableEntityHttpException('plan_prevention.not_owner');
        }

        $hasRefus = false;
        foreach ($plan->getDecisionsHse() as $decision) {
            if ($decision->getDecision() === DecisionHse::REFUSE) {
                $hasRefus = true;
                break;
            }
        }
        if (!$hasRefus) {
            throw new UnprocessableEntityHttpException('plan_prevention.no_previous_refus');
        }

        $missingTypes = $this->findMissingDocumentTypes($plan);
        if (!empty($missingTypes)) {
            throw new UnprocessableEntityHttpException(sprintf(
                'documents_manquants: %s',
                implode(', ', array_map(static fn(TypeDocumentPrevention $t) => $t->value, $missingTypes)),
            ));
        }

        $numeroVersion = $this->versionRepository->findNextNumeroVersion($plan);
        $motif         = $this->requestStack->getCurrentRequest()?->request->get('motifResoumission');

        $version = new VersionPlanPrevention();
        $version->setPlanPrevention($plan);
        $version->setNumeroVersion($numeroVersion);
        $version->setSnapshotData($this->buildSnapshot($plan));
        $version->setCreatedBy($user);
        $version->setCreatedAt(new \DateTimeImmutable());
        $version->setMotifResoumission($motif !== '' ? $motif : null);

        $this->entityManager->persist($version);

        $plan->setStatut(StatutPlanPrevention::SOUMIS);

        $result = $this->persistProcessor->process($plan, $operation, $uriVariables, $context);

        $this->notificationService->notifierHse($plan, $numeroVersion);

        return $result;
    }

    /** @return TypeDocumentPrevention[] */
    private function findMissingDocumentTypes(PlanPrevention $plan): array
    {
        $presentTypes = array_map(
            static fn($doc) => $doc->getType(),
            $plan->getDocuments()->toArray(),
        );

        return array_values(array_filter(
            self::REQUIRED_DOCUMENT_TYPES,
            static fn(TypeDocumentPrevention $required) => !in_array($required, $presentTypes, true),
        ));
    }

    private function buildSnapshot(PlanPrevention $plan): array
    {
        return [
            'reference'         => $plan->getReference(),
            'codeSite'          => $plan->getCodeSite(),
            'localite'          => $plan->getLocalite(),
            'activitePlanifiee' => $plan->getActivitePlanifiee(),
            'dateDebut'         => $plan->getDateDebut()?->format(\DateTimeInterface::ATOM),
            'dateFin'           => $plan->getDateFin()?->format(\DateTimeInterface::ATOM),
            'statut'            => $plan->getStatut()->value,
            'risques'           => array_map(
                static fn($r) => [
                    'description'       => $r->getDescription(),
                    'gravite'           => $r->getGravite(),
                    'probabilite'       => $r->getProbabilite(),
                    'mesuresPreventives' => $r->getMesuresPreventives(),
                ],
                $plan->getRisques()->toArray(),
            ),
            'documents'         => array_map(
                static fn($d) => [
                    'type'     => $d->getType()->value,
                    'filePath' => $d->getFilePath(),
                ],
                $plan->getDocuments()->toArray(),
            ),
        ];
    }
}
