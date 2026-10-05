<?php

declare(strict_types=1);

namespace App\Tests\Unit\Api\Processor;

use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Processor\PlanPreventionResoumettreProcessor;
use App\Domain\PlanPrevention\Entity\DecisionHsePlanPrevention;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Enum\DecisionHse;
use App\Domain\PlanPrevention\Enum\StatutPlanPrevention;
use App\Domain\PlanPrevention\Repository\VersionPlanPreventionRepository;
use App\Domain\PlanPrevention\Service\PlanPreventionConsultationGuard;
use App\Domain\PlanPrevention\Service\PlanPreventionNotificationService;
use App\Domain\PlanPrevention\Service\PlanPreventionSubmissionValidator;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class PlanPreventionResoumettreProcessorTest extends TestCase
{
    private ProcessorInterface&MockObject $persistProcessor;
    private EntityManagerInterface&MockObject $entityManager;
    private VersionPlanPreventionRepository&MockObject $versionRepository;
    private PlanPreventionNotificationService&MockObject $notificationService;
    private PlanPreventionSubmissionValidator&MockObject $submissionValidator;
    private PlanPreventionConsultationGuard&MockObject $consultationGuard;
    private RequestStack $requestStack;
    private TokenStorageInterface&MockObject $tokenStorage;
    private PlanPreventionResoumettreProcessor $processor;
    private User $user;

    protected function setUp(): void
    {
        $this->persistProcessor = $this->createMock(ProcessorInterface::class);
        $this->persistProcessor->method('process')->willReturnArgument(0);

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->versionRepository = $this->createMock(VersionPlanPreventionRepository::class);
        $this->versionRepository->method('findNextNumeroVersion')->willReturn(2);
        $this->notificationService = $this->createMock(PlanPreventionNotificationService::class);
        $this->submissionValidator = $this->createMock(PlanPreventionSubmissionValidator::class);
        $this->consultationGuard = $this->createMock(PlanPreventionConsultationGuard::class);
        $this->requestStack = new RequestStack();
        $this->requestStack->push(new Request());
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);

        $this->user = (new User())->setEmail('prestataire@ex.com');
        $this->tokenStorage->method('getToken')->willReturn(
            new UsernamePasswordToken($this->user, 'main', ['ROLE_PRESTATAIRE'])
        );

        $this->processor = new PlanPreventionResoumettreProcessor(
            $this->persistProcessor,
            $this->entityManager,
            $this->tokenStorage,
            $this->versionRepository,
            $this->notificationService,
            $this->requestStack,
            $this->submissionValidator,
            $this->consultationGuard,
        );
    }

    public function testResoumettreFromACorrigerSetsSoumis(): void
    {
        $plan = (new PlanPrevention())
            ->setStatut(StatutPlanPrevention::A_CORRIGER)
            ->setCreatedBy($this->user)
            ->setReference('PP-1')
            ->setCodeSite('SITE1')
            ->setLocalite('Antananarivo')
            ->setActivitePlanifiee('Travaux');

        $decision = (new DecisionHsePlanPrevention())
            ->setDecision(DecisionHse::REFUSE)
            ->setCommentaire('À revoir')
            ->setSignatureElectronique(str_repeat('a', 64))
            ->setDecidedAt(new \DateTimeImmutable())
            ->setDecidePar($this->user)
            ->setPlanPrevention($plan);
        $plan->getDecisionsHse()->add($decision);

        $this->submissionValidator->expects($this->once())->method('assertSubmittable')->with($plan);
        $this->consultationGuard->expects($this->once())->method('resetConsultations')->with($plan);
        $this->notificationService->expects($this->once())->method('notifierHse')->with($plan, 2);
        $this->entityManager->expects($this->once())->method('persist');

        $this->processor->process($plan, new Post());

        $this->assertSame(StatutPlanPrevention::SOUMIS, $plan->getStatut());
    }

    public function testResoumettreFromBrouillonStillWorks(): void
    {
        $plan = (new PlanPrevention())
            ->setStatut(StatutPlanPrevention::BROUILLON)
            ->setCreatedBy($this->user)
            ->setReference('PP-2')
            ->setCodeSite('SITE1')
            ->setLocalite('Antananarivo')
            ->setActivitePlanifiee('Travaux');

        $decision = (new DecisionHsePlanPrevention())
            ->setDecision(DecisionHse::REFUSE)
            ->setCommentaire('Ancien refus')
            ->setSignatureElectronique(str_repeat('b', 64))
            ->setDecidedAt(new \DateTimeImmutable())
            ->setDecidePar($this->user)
            ->setPlanPrevention($plan);
        $plan->getDecisionsHse()->add($decision);

        $this->processor->process($plan, new Post());

        $this->assertSame(StatutPlanPrevention::SOUMIS, $plan->getStatut());
    }
}
