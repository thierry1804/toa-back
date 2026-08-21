<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Enum\StatutPlanPrevention;
use App\Domain\PlanPrevention\Enum\TypeDocumentPrevention;
use App\Domain\PlanPrevention\Service\PlanPreventionNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class PlanPreventionSoumettreProcessor implements ProcessorInterface
{
    private const REQUIRED_DOCUMENT_TYPES = [
        TypeDocumentPrevention::PLAN_URGENCE,
        TypeDocumentPrevention::FDS,
        TypeDocumentPrevention::LISTE_INTERVENANTS,
        TypeDocumentPrevention::ATTESTATION_HSE,
        TypeDocumentPrevention::FICHE_CONFORMITE,
        TypeDocumentPrevention::LISTE_VEHICULES,
    ];

    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly EntityManagerInterface $entityManager,
        private readonly PlanPreventionNotificationService $notificationService,
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

        $missingTypes = $this->findMissingDocumentTypes($plan);

        if (!empty($missingTypes)) {
            $missingLabels = array_map(
                static fn(TypeDocumentPrevention $t) => $t->value,
                $missingTypes,
            );

            throw new UnprocessableEntityHttpException(
                sprintf('documents_manquants: %s', implode(', ', $missingLabels)),
            );
        }

        $plan->setStatut(StatutPlanPrevention::SOUMIS);

        $result = $this->persistProcessor->process($plan, $operation, $uriVariables, $context);

        $this->notificationService->notifierChefProjet($plan);

        return $result;
    }

    /** @return TypeDocumentPrevention[] */
    private function findMissingDocumentTypes(PlanPrevention $plan): array
    {
        $presentTypes = array_map(
            static fn($doc) => $doc->getType(),
            $plan->getDocuments()->toArray(),
        );

        return array_filter(
            self::REQUIRED_DOCUMENT_TYPES,
            static fn(TypeDocumentPrevention $required) => !in_array($required, $presentTypes, true),
        );
    }
}
