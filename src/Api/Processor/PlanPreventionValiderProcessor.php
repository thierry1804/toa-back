<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\PlanPrevention\Entity\DecisionHsePlanPrevention;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Enum\DecisionHse;
use App\Domain\PlanPrevention\Enum\StatutPlanPrevention;
use App\Domain\PlanPrevention\Enum\TypeDocumentPrevention;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class PlanPreventionValiderProcessor implements ProcessorInterface
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

        if ($plan->getStatut() !== StatutPlanPrevention::EXAMINE) {
            throw new UnprocessableEntityHttpException('Plan non examiné par Chef de Projet');
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

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User) {
            throw new UnprocessableEntityHttpException('plan_prevention.user_not_found');
        }

        $now = new \DateTimeImmutable();
        $signature = hash('sha256', (string) $user->getId() . $plan->getId()->toRfc4122() . $now->getTimestamp());

        $decision = new DecisionHsePlanPrevention();
        $decision->setPlanPrevention($plan);
        $decision->setDecidePar($user);
        $decision->setDecision(DecisionHse::VALIDE);
        $decision->setSignatureElectronique($signature);
        $decision->setDecidedAt($now);

        $this->entityManager->persist($decision);

        $plan->setStatut(StatutPlanPrevention::VALIDE_HSE);

        return $this->persistProcessor->process($plan, $operation, $uriVariables, $context);
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
}
