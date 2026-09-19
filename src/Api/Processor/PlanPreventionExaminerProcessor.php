<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\PlanPrevention\Entity\ExamenPlanPrevention;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Enum\StatutPlanPrevention;
use App\Domain\PlanPrevention\Service\PlanPreventionConsultationGuard;
use App\Domain\PlanPrevention\Service\PlanPreventionNotificationService;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class PlanPreventionExaminerProcessor implements ProcessorInterface
{
    private const STATUTS_VALIDES = [
        StatutPlanPrevention::SOUMIS,
        StatutPlanPrevention::EN_COURS_DE_VALIDATION,
    ];

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

        if (!in_array($plan->getStatut(), self::STATUTS_VALIDES, true)) {
            throw new UnprocessableEntityHttpException('Statut invalide pour examen');
        }

        $this->consultationGuard->assertAllConsulted($plan);

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User) {
            throw new UnprocessableEntityHttpException('plan_prevention.user_not_found');
        }

        $commentaire = $this->extractCommentaire();

        $plan->setStatut(StatutPlanPrevention::EXAMINE);
        // Le HSE doit consulter à son tour chaque pièce avant de valider ou refuser.
        $this->consultationGuard->resetConsultations($plan);

        $examen = new ExamenPlanPrevention();
        $examen->setPlanPrevention($plan);
        $examen->setExaminePar($user);
        $examen->setExamineAt(new \DateTimeImmutable());
        $examen->setCommentaire($commentaire);

        $this->entityManager->persist($examen);

        $result = $this->persistProcessor->process($plan, $operation, $uriVariables, $context);

        $this->notificationService->notifyHseUsers($plan, $user);

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
