<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\PlanPrevention\Entity\ModeOperatoire;
use App\Domain\PlanPrevention\Entity\SectionPrevention;
use App\Domain\PlanPrevention\Enum\StatutPlanPrevention;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * R-04 : mêmes raisons que SectionPreventionCreateProcessor — pas d'objet pour
 * le voter en POST, ownership "prestataire seul" + statut BROUILLON vérifiés ici,
 * via la section pour remonter au plan de prévention.
 */
final class ModeOperatoireCreateProcessor implements ProcessorInterface
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
        if (!$data instanceof ModeOperatoire) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $section = $this->entityManager->find(SectionPrevention::class, $uriVariables['sectionId'] ?? null);
        if (!$section instanceof SectionPrevention) {
            throw new UnprocessableEntityHttpException('section_prevention.not_found');
        }

        $plan = $section->getPlanPrevention();
        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User || $plan?->getCreatedBy()?->getUserIdentifier() !== $user->getUserIdentifier()) {
            throw new AccessDeniedException('error.voter.access_denied');
        }

        if ($plan->getStatut() !== StatutPlanPrevention::BROUILLON) {
            throw new AccessDeniedException('plan_prevention.statut_not_brouillon');
        }

        $data->setSection($section);

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
