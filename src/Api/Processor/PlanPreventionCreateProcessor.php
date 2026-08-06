<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\ActivityPlanning\Entity\ActivityPlanning;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Enum\StatutPlanPrevention;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class PlanPreventionCreateProcessor implements ProcessorInterface
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
        if (!$data instanceof PlanPrevention) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $this->validateDates($data);

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User) {
            throw new UnprocessableEntityHttpException('user_not_authenticated');
        }

        $data->setReference($this->generateReference());
        $data->setReferenceActivite($this->generateReferenceActivite());
        $data->setStatut(StatutPlanPrevention::BROUILLON);
        $data->setCreatedBy($user);

        if ($data->getPlanificationId() !== null) {
            $planification = $this->entityManager->find(ActivityPlanning::class, $data->getPlanificationId());
            if ($planification !== null) {
                $data->setTypeIntervention($planification->getTypeIntervention());
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    private function generateReference(): string
    {
        $year = (int) date('Y');

        $result = $this->entityManager->getConnection()->fetchOne(
            "SELECT MAX(CAST(SUBSTRING(reference FROM '\d{4}/PPHSSES-TOA-(\d{4})') AS INTEGER))
             FROM plan_prevention
             WHERE reference LIKE :pattern",
            ['pattern' => $year . '/PPHSSES-TOA-%'],
        );

        $next = (($result === null || $result === false) ? 0 : (int) $result) + 1;

        return sprintf('%d/PPHSSES-TOA-%04d', $year, $next);
    }

    private function generateReferenceActivite(): string
    {
        $year = (int) date('Y');

        $result = $this->entityManager->getConnection()->fetchOne(
            "SELECT MAX(CAST(SUBSTRING(reference_activite FROM 'ACT-\d{4}-(\d{4})') AS INTEGER))
             FROM plan_prevention
             WHERE reference_activite LIKE :pattern",
            ['pattern' => 'ACT-' . $year . '-%'],
        );

        $next = (($result === null || $result === false) ? 0 : (int) $result) + 1;

        return sprintf('ACT-%d-%04d', $year, $next);
    }

    private function validateDates(PlanPrevention $data): void
    {
        $debut = $data->getDateDebut();
        $fin   = $data->getDateFin();

        if ($debut !== null && $fin !== null && $fin <= $debut) {
            throw new UnprocessableEntityHttpException('date_fin_must_be_after_date_debut');
        }
    }
}
