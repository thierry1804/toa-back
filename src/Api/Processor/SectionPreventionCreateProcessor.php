<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Entity\SectionPrevention;
use App\Domain\PlanPrevention\Enum\StatutPlanPrevention;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * R-04 : le voter (PLAN_PREVENTION_EDIT_OPERATOIRE) ne reçoit aucun objet en POST
 * (la section n'existe pas encore) — l'ownership stricte "prestataire seul" et le
 * statut BROUILLON du plan ciblé sont donc vérifiés ici.
 */
final class SectionPreventionCreateProcessor implements ProcessorInterface
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
        if (!$data instanceof SectionPrevention) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $plan = $this->entityManager->find(PlanPrevention::class, $uriVariables['planPreventionId'] ?? null);
        if (!$plan instanceof PlanPrevention) {
            throw new UnprocessableEntityHttpException('plan_prevention.not_found');
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User || $plan->getCreatedBy()?->getUserIdentifier() !== $user->getUserIdentifier()) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        if ($plan->getStatut() !== StatutPlanPrevention::BROUILLON) {
            throw new AccessDeniedException('plan_prevention.statut_not_brouillon');
        }

        $data->setPlanPrevention($plan);

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
