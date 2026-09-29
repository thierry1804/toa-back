<?php

declare(strict_types=1);

namespace App\Tests\Unit\Api\Processor;

use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Processor\PlanPreventionSoumettreProcessor;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Service\PlanPreventionChefProjetResolver;
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
    private PlanPreventionNotificationService&MockObject $notificationService;
    private PlanPreventionChefProjetResolver&MockObject $chefProjetResolver;
    private PlanPreventionSoumettreProcessor $processor;

    protected function setUp(): void
    {
        $this->persistProcessor = $this->createMock(ProcessorInterface::class);
        $this->persistProcessor->method('process')->willReturnArgument(0);

        $this->notificationService = $this->createMock(PlanPreventionNotificationService::class);
        $this->chefProjetResolver = $this->createMock(PlanPreventionChefProjetResolver::class);

        $this->processor = new PlanPreventionSoumettreProcessor(
            $this->persistProcessor,
            $this->createMock(EntityManagerInterface::class),
            $this->notificationService,
            $this->createMock(PlanPreventionSubmissionValidator::class),
            $this->createMock(PlanPreventionConsultationGuard::class),
            $this->chefProjetResolver,
        );
    }

    public function testBackfillsTheChefProjetBeforePersistingAndNotifying(): void
    {
        $chefProjet = (new User())->setEmail('chef@toa.mg');
        $plan = (new PlanPrevention())->setPlanificationId(42);

        $this->chefProjetResolver->expects($this->once())
            ->method('backfill')
            ->with($plan)
            ->willReturnCallback(static function (PlanPrevention $p) use ($chefProjet): User {
                $p->setChefProjet($chefProjet);

                return $chefProjet;
            });

        $this->notificationService->expects($this->once())
            ->method('notifierChefProjet')
            ->with($this->callback(static fn (PlanPrevention $p): bool => $p->getChefProjet() === $chefProjet));

        $this->processor->process($plan, new Post());
    }

    public function testStillNotifiesEvenWhenTheChefProjetCannotBeResolved(): void
    {
        $plan = new PlanPrevention();

        $this->chefProjetResolver->expects($this->once())->method('backfill')->with($plan)->willReturn(null);
        $this->notificationService->expects($this->once())->method('notifierChefProjet')->with($plan);

        $this->processor->process($plan, new Post());

        $this->assertNull($plan->getChefProjet());
    }
}
