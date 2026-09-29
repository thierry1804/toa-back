<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\ActivityPlanning\Entity\ActivityPlanning;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Enum\StatutPlanPrevention;
use App\Domain\PlanPrevention\Service\PlanPreventionConsultationGuard;
use App\Domain\PlanPrevention\Service\PlanPreventionNotificationService;
use App\Domain\PlanPrevention\Service\PlanPreventionSubmissionValidator;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class PlanPreventionSoumettreProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly EntityManagerInterface $entityManager,
        private readonly PlanPreventionNotificationService $notificationService,
        private readonly PlanPreventionSubmissionValidator $submissionValidator,
        private readonly PlanPreventionConsultationGuard $consultationGuard,
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

        $this->submissionValidator->assertSubmittable($plan);

        // Filet de sécurité pour les plans créés avant que la création n'affecte
        // automatiquement le chef de projet de la planification (ou dont le lien
        // vers la planification a été renseigné après coup) : sans ce repli, la
        // notification de soumission ne partirait à personne.
        if ($plan->getChefProjet() === null && $plan->getPlanificationId() !== null) {
            $planification = $this->entityManager->find(ActivityPlanning::class, $plan->getPlanificationId());
            if ($planification?->getCreatedBy() instanceof User) {
                $plan->setChefProjet($planification->getCreatedBy());
            }
        }

        $plan->setStatut(StatutPlanPrevention::SOUMIS);
        $plan->setSoumisAt(new \DateTimeImmutable());
        $plan->setValidatedAt(null);
        $this->consultationGuard->resetConsultations($plan);

        $result = $this->persistProcessor->process($plan, $operation, $uriVariables, $context);

        $this->notificationService->notifierChefProjet($plan);

        return $result;
    }
}
