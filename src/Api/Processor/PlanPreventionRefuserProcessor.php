<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\PlanPrevention\Entity\DecisionHsePlanPrevention;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Enum\DecisionHse;
use App\Domain\PlanPrevention\Enum\StatutPlanPrevention;
use App\Domain\PlanPrevention\Service\PlanPreventionConsultationGuard;
use App\Domain\PlanPrevention\Service\PlanPreventionNotificationService;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class PlanPreventionRefuserProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly EntityManagerInterface $entityManager,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly PlanPreventionNotificationService $notificationService,
        private readonly RequestStack $requestStack,
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

        if ($plan->getStatut() !== StatutPlanPrevention::EXAMINE) {
            throw new UnprocessableEntityHttpException('Plan non examiné');
        }

        $commentaire = $this->extractCommentaire();

        if ($commentaire === null || $commentaire === '') {
            throw new UnprocessableEntityHttpException('Commentaire obligatoire en cas de refus');
        }

        $this->consultationGuard->assertAllConsulted($plan);

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User) {
            throw new UnprocessableEntityHttpException('plan_prevention.user_not_found');
        }

        $now = new \DateTimeImmutable();
        $signature = hash('sha256', (string) $user->getId() . $plan->getId()->toRfc4122() . $now->getTimestamp());

        $decision = new DecisionHsePlanPrevention();
        $decision->setPlanPrevention($plan);
        $decision->setDecidePar($user);
        $decision->setDecision(DecisionHse::REFUSE);
        $decision->setCommentaire($commentaire);
        $decision->setSignatureElectronique($signature);
        $decision->setDecidedAt($now);

        $this->entityManager->persist($decision);

        $plan->setStatut(StatutPlanPrevention::BROUILLON);

        $result = $this->persistProcessor->process($plan, $operation, $uriVariables, $context);

        $this->notificationService->notifierPrestataire($plan, $commentaire);

        return $result;
    }

    private function extractCommentaire(): ?string
    {
        $content = $this->requestStack->getCurrentRequest()?->getContent() ?? '';
        if ($content === '') {
            return null;
        }

        $body = json_decode($content, true);
        if (!is_array($body)) {
            return null;
        }

        $commentaire = $body['commentaire'] ?? null;

        return is_string($commentaire) && $commentaire !== '' ? $commentaire : null;
    }
}
