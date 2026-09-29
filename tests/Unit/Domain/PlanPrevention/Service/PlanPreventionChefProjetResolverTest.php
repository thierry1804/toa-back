<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\PlanPrevention\Service;

use App\Domain\ActivityPlanning\Entity\ActivityPlanning;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Service\PlanPreventionChefProjetResolver;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PlanPreventionChefProjetResolverTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private PlanPreventionChefProjetResolver $resolver;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->resolver = new PlanPreventionChefProjetResolver($this->entityManager);
    }

    private function planification(?User $createdBy): ActivityPlanning
    {
        return (new ActivityPlanning())->setCreatedBy($createdBy);
    }

    public function testReturnsTheAlreadyAssignedChefProjetWithoutLookingAtThePlanification(): void
    {
        $assigned = (new User())->setEmail('assigne@toa.mg');
        $plan = (new PlanPrevention())->setPlanificationId(42)->setChefProjet($assigned);

        $this->entityManager->expects($this->never())->method('find');

        $this->assertSame($assigned, $this->resolver->resolve($plan));
    }

    public function testResolvesFromThePlanificationsCreatorWhenChefProjetIsMissing(): void
    {
        $chefProjet = (new User())->setEmail('chef@toa.mg');
        $this->entityManager->method('find')->with(ActivityPlanning::class, 42)->willReturn($this->planification($chefProjet));

        $plan = (new PlanPrevention())->setPlanificationId(42);

        $this->assertSame($chefProjet, $this->resolver->resolve($plan));
    }

    public function testReturnsNullWhenThereIsNoLinkedPlanification(): void
    {
        $plan = new PlanPrevention();

        $this->assertNull($this->resolver->resolve($plan));
    }

    public function testBackfillAssignsAndReturnsTheResolvedChefProjet(): void
    {
        $chefProjet = (new User())->setEmail('chef@toa.mg');
        $this->entityManager->method('find')->with(ActivityPlanning::class, 42)->willReturn($this->planification($chefProjet));

        $plan = (new PlanPrevention())->setPlanificationId(42);

        $result = $this->resolver->backfill($plan);

        $this->assertSame($chefProjet, $result);
        $this->assertSame($chefProjet, $plan->getChefProjet());
    }

    public function testBackfillDoesNotOverrideAnAlreadyAssignedChefProjet(): void
    {
        $assigned = (new User())->setEmail('assigne@toa.mg');
        $plan = (new PlanPrevention())->setPlanificationId(42)->setChefProjet($assigned);

        $this->entityManager->expects($this->never())->method('find');

        $this->assertSame($assigned, $this->resolver->backfill($plan));
    }

    public function testBackfillLeavesChefProjetNullWhenNothingCanBeResolved(): void
    {
        $plan = new PlanPrevention();

        $this->assertNull($this->resolver->backfill($plan));
        $this->assertNull($plan->getChefProjet());
    }
}
