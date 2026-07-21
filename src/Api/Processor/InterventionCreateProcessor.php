<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Intervention\Entity\EvaluationRisque;
use App\Domain\Intervention\Entity\Intervention;
use App\Domain\Intervention\Enum\StatutIntervention;
use App\Domain\PermitTravail\Enum\StatutPermitTravail;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class InterventionCreateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly EntityManagerInterface $entityManager,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Intervention) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User) {
            throw new UnprocessableEntityHttpException('intervention.user_not_authenticated');
        }

        $permit = $data->getPermitTravail();
        if ($permit === null) {
            throw new UnprocessableEntityHttpException('intervention.permit_travail_required');
        }

        if ($permit->getStatut() !== StatutPermitTravail::VALIDE_HSE) {
            throw new UnprocessableEntityHttpException('intervention.permit_travail_not_valide');
        }

        $data->setStatut(StatutIntervention::EN_PREPARATION);
        $data->setCreatedBy($user);

        $this->importRisquesFromPlan($data, $user);

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    private function importRisquesFromPlan(Intervention $intervention, User $user): void
    {
        $plan = $intervention->getPermitTravail()?->getPlanPrevention();
        if ($plan === null) {
            return;
        }

        foreach ($plan->getRisques() as $risque) {
            $eval = new EvaluationRisque();
            $eval->setRisqueSource($risque);
            $eval->setDescription((string) $risque->getDescription());
            $eval->setGravite((int) $risque->getGravite());
            $eval->setProbabilite((int) $risque->getProbabilite());
            $eval->setMesuresConfirmees($risque->getMesuresPreventives());
            $eval->setEstReevalue(false);
            $eval->setCreatedBy($user);
            $intervention->addEvaluation($eval);
        }
    }
}
