<?php

declare(strict_types=1);

namespace App\Tests\Unit\Api\Processor;

use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Processor\PlanPreventionSoumettreProcessor;
use App\Domain\ActivityPlanning\Entity\ActivityPlanning;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Service\PlanPreventionConsultationGuard;
use App\Domain\PlanPrevention\Service\PlanPreventionNotificationService;
use App\Domain\PlanPrevention\Service\PlanPreventionSubmissionValidator;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PlanPreventionSoumettreProcessorTest extends TestCase
{
    private ProcessorInterface&MockObject $persistProcessor;
    private EntityManagerInterface&MockObject $entityManager;
    private PlanPreventionNotificationService&MockObject $notificationService;
    private PlanPreventionSoumettreProcessor $processor;

    protected function setUp(): void
    {
        $this->persistProcessor = $this->createMock(ProcessorInterface::class);
        $this->persistProcessor->method('process')->willReturnArgument(0);

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->notificationService = $this->createMock(PlanPreventionNotificationService::class);

        $this->processor = new PlanPreventionSoumettreProcessor(
            $this->persistProcessor,
            $this->entityManager,
            $this->notificationService,
            $this->createMock(PlanPreventionSubmissionValidator::class),
            $this->createMock(PlanPreventionConsultationGuard::class),
        );
    }

    private function planification(User $createdBy): ActivityPlanning
    {
        $planification = (new ActivityPlanning())->setCreatedBy($createdBy);
        $ref = new \ReflectionProperty($planification, 'id');
        $ref->setAccessible(true);
        $ref->setValue($planification, 42);

        return $planification;
    }

    public function testFallsBackToThePlanificationsCreatorWhenChefProjetIsMissing(): void
    {
        $chefProjet = (new User())->setEmail('chef@toa.mg');
        $this->entityManager->method('find')->with(ActivityPlanning::class, 42)->willReturn($this->planification($chefProjet));

        $plan = (new PlanPrevention())->setPlanificationId(42);

        $this->notificationService->expects($this->once())
            ->method('notifierChefProjet')
            ->with($this->callback(static fn (PlanPrevention $p): bool => $p->getChefProjet() === $chefProjet));

        $this->processor->process($plan, new Post());

        $this->assertSame($chefProjet, $plan->getChefProjet());
    }

    public function testDoesNotOverrideAnAlreadyAssignedChefProjet(): void
    {
        $assigned = (new User())->setEmail('assigne@toa.mg');
        $planningCreator = (new User())->setEmail('autre@toa.mg');
        $this->entityManager->method('find')->with(ActivityPlanning::class, 42)->willReturn($this->planification($planningCreator));

        $plan = (new PlanPrevention())->setPlanificationId(42)->setChefProjet($assigned);

        $this->processor->process($plan, new Post());

        $this->assertSame($assigned, $plan->getChefProjet());
    }

    public function testLeavesChefProjetNullWhenThereIsNoLinkedPlanificationEither(): void
    {
        $plan = new PlanPrevention();

        $this->processor->process($plan, new Post());

        $this->assertNull($plan->getChefProjet());
    }
}
