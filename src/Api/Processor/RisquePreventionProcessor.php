<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Entity\RisquePrevention;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class RisquePreventionProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof RisquePrevention) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $planPreventionId = $uriVariables['planPreventionId'] ?? null;
        $plan = $this->entityManager->find(PlanPrevention::class, $planPreventionId);

        if (!$plan instanceof PlanPrevention) {
            throw new UnprocessableEntityHttpException('plan_prevention.not_found');
        }

        $data->setPlanPrevention($plan);
        $this->assertModeOperatoireBelongsToPlan($data, $plan);

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    /**
     * Les plans sans phases propres (antérieurs à la saisie des modes opératoires
     * dans le plan) conservent l'ancienne référence libre.
     */
    private function assertModeOperatoireBelongsToPlan(RisquePrevention $risque, PlanPrevention $plan): void
    {
        $modeId = $risque->getTachePlanifieeId();
        if ($modeId === null || $modeId === '' || $plan->getSections()->isEmpty()) {
            return;
        }

        foreach ($plan->getSections() as $phase) {
            foreach ($phase->getModesOperatoires() as $mode) {
                if ((string) $mode->getId() === $modeId) {
                    return;
                }
            }
        }

        throw new UnprocessableEntityHttpException('tache_planifiee_introuvable');
    }
}
