<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Offline\Service;

use App\Domain\ActivityPlanning\Entity\ActivityPlanning;
use App\Domain\Intervention\Entity\Intervention;
use App\Domain\Offline\Repository\TombstoneRecordRepository;
use App\Domain\Offline\Service\OfflineSnapshotDataProvider;
use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\Referentiel\Repository\CategorieRisqueRepository;
use App\Domain\User\Entity\User;
use App\Domain\Referentiel\Repository\InstallationEquipementRepository;
use App\Domain\Referentiel\Repository\SiteRepository;
use App\Doctrine\Extension\ActivityPlanningExtension;
use App\Doctrine\Extension\CurrentUserExtension;
use App\Doctrine\Extension\EntrepriseCollectionExtension;
use App\Doctrine\Extension\InterventionExtension;
use App\Doctrine\Extension\PlanPreventionExtension;
use App\Doctrine\Extension\UserCollectionExtension;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class OfflineSnapshotDataProviderTest extends TestCase
{
    public function testIsEntityModuleAccessibleReturnsTrueWhenVoterGrants(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->with('PERMIT_TRAVAIL_VIEW')->willReturn(true);

        $provider = $this->buildProvider($security);

        $this->assertTrue($provider->isEntityModuleAccessible('permitsTravail'));
    }

    /**
     * Les voters de ce projet lèvent AccessDeniedException directement sur refus
     * (au lieu de retourner false, cf. PlanPreventionVoter/InterventionVoter). Un
     * module non autorisé doit être traité comme "absent", jamais faire planter
     * toute la requête snapshot.
     */
    public function testIsEntityModuleAccessibleCatchesAccessDeniedExceptionAndReturnsFalse(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')
            ->with('ACTIVITY_PLANNING_VIEW')
            ->willThrowException(new AccessDeniedException('error.voter.access_denied'));

        $provider = $this->buildProvider($security);

        $this->assertFalse($provider->isEntityModuleAccessible('activityPlannings'));
    }

    public function testUnknownModuleIsNeverAccessible(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects($this->never())->method('isGranted');

        $provider = $this->buildProvider($security);

        $this->assertFalse($provider->isEntityModuleAccessible('notAModule'));
    }

    public function testAccessibleEntityModulesFiltersOutDeniedAndThrowingOnes(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturnCallback(
            fn (string $attribute): bool => match ($attribute) {
                'PLAN_PREVENTION_VIEW'   => true,
                'PERMIT_TRAVAIL_VIEW'    => throw new AccessDeniedException(),
                'INTERVENTION_VIEW'      => false,
                'ACTIVITY_PLANNING_VIEW' => true,
                default                  => false,
            },
        );

        $provider = $this->buildProvider($security);

        $this->assertSame(['plansPrevention', 'activityPlannings'], $provider->accessibleEntityModules());
    }

    public function testScopedQueryBuilderDispatchesToTheMatchingExtensionOnly(): void
    {
        $permitTravailExtension = $this->createMock(CurrentUserExtension::class);
        $permitTravailExtension->expects($this->once())
            ->method('applyToCollection')
            ->with($this->isInstanceOf(QueryBuilder::class), $this->anything(), PermitTravail::class);

        $planPreventionExtension = $this->createMock(PlanPreventionExtension::class);
        $planPreventionExtension->expects($this->never())->method('applyToCollection');

        $interventionExtension = $this->createMock(InterventionExtension::class);
        $interventionExtension->expects($this->never())->method('applyToCollection');

        $activityPlanningExtension = $this->createMock(ActivityPlanningExtension::class);
        $activityPlanningExtension->expects($this->never())->method('applyToCollection');

        $provider = $this->buildProvider(
            $this->createMock(Security::class),
            planPreventionExtension: $planPreventionExtension,
            permitTravailExtension: $permitTravailExtension,
            interventionExtension: $interventionExtension,
            activityPlanningExtension: $activityPlanningExtension,
        );

        $queryBuilder = $provider->scopedQueryBuilder('permitsTravail', null);

        $this->assertStringContainsString('FROM ' . PermitTravail::class, $queryBuilder->getDQL());
    }

    public function testAccessibleEntityModulesIncludesUsersAndEntreprisesWhenGranted(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturnCallback(
            fn (string $attribute): bool => match ($attribute) {
                'USER_VIEW', 'ENTREPRISE_VIEW' => true,
                default                        => false,
            },
        );

        $provider = $this->buildProvider($security);

        $this->assertContains('users', $provider->accessibleEntityModules());
        $this->assertContains('entreprises', $provider->accessibleEntityModules());
    }

    public function testScopedQueryBuilderDispatchesUsersToUserCollectionExtension(): void
    {
        $userExtension = $this->createMock(UserCollectionExtension::class);
        $userExtension->expects($this->once())
            ->method('applyToCollection')
            ->with($this->isInstanceOf(QueryBuilder::class), $this->anything(), User::class);

        $entrepriseExtension = $this->createMock(EntrepriseCollectionExtension::class);
        $entrepriseExtension->expects($this->never())->method('applyToCollection');

        $provider = $this->buildProvider($this->createMock(Security::class), userExtension: $userExtension, entrepriseExtension: $entrepriseExtension);

        $queryBuilder = $provider->scopedQueryBuilder('users', null);

        $this->assertStringContainsString('FROM ' . User::class, $queryBuilder->getDQL());
    }

    public function testTombstoneIdsMapsEntreprisesModuleToEntrepriseType(): void
    {
        $since = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');

        $tombstoneRepository = $this->createMock(TombstoneRecordRepository::class);
        $tombstoneRepository->expects($this->once())
            ->method('findDeletedIdsSince')
            ->with('entreprise', $since)
            ->willReturn([]);

        $provider = $this->buildProvider($this->createMock(Security::class), tombstoneRepository: $tombstoneRepository);

        $provider->tombstoneIds('entreprises', $since);
    }

    public function testScopedQueryBuilderAppliesSinceFilterOnUpdatedAt(): void
    {
        $provider = $this->buildProvider($this->createMock(Security::class));
        $since    = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');

        $queryBuilder = $provider->scopedQueryBuilder('interventions', $since);

        $this->assertStringContainsString('e.updatedAt >= :since', $queryBuilder->getDQL());
        $this->assertSame($since, $queryBuilder->getParameter('since')?->getValue());
    }

    public function testTombstoneIdsReturnsEmptyForModuleWithoutTombstoneTracking(): void
    {
        $tombstoneRepository = $this->createMock(TombstoneRecordRepository::class);
        $tombstoneRepository->expects($this->never())->method('findDeletedIdsSince');

        $provider = $this->buildProvider($this->createMock(Security::class), tombstoneRepository: $tombstoneRepository);

        $this->assertSame([], $provider->tombstoneIds('sites', new \DateTimeImmutable()));
    }

    public function testTombstoneIdsDelegatesToRepositoryWithMappedType(): void
    {
        $since = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');

        $tombstoneRepository = $this->createMock(TombstoneRecordRepository::class);
        $tombstoneRepository->expects($this->once())
            ->method('findDeletedIdsSince')
            ->with('plan_prevention', $since)
            ->willReturn(['019ffb26-ebbb-7baa-9573-52e13a752d49']);

        $provider = $this->buildProvider($this->createMock(Security::class), tombstoneRepository: $tombstoneRepository);

        $this->assertSame(['019ffb26-ebbb-7baa-9573-52e13a752d49'], $provider->tombstoneIds('plansPrevention', $since));
    }

    private function buildProvider(
        Security $security,
        ?PlanPreventionExtension $planPreventionExtension = null,
        ?CurrentUserExtension $permitTravailExtension = null,
        ?InterventionExtension $interventionExtension = null,
        ?ActivityPlanningExtension $activityPlanningExtension = null,
        ?UserCollectionExtension $userExtension = null,
        ?EntrepriseCollectionExtension $entrepriseExtension = null,
        ?TombstoneRecordRepository $tombstoneRepository = null,
    ): OfflineSnapshotDataProvider {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getExpressionBuilder')->willReturn(new Expr());
        $entityManager->method('createQueryBuilder')->willReturnCallback(
            fn (): QueryBuilder => new QueryBuilder($entityManager),
        );

        return new OfflineSnapshotDataProvider(
            $entityManager,
            $security,
            $planPreventionExtension ?? $this->createMock(PlanPreventionExtension::class),
            $permitTravailExtension ?? $this->createMock(CurrentUserExtension::class),
            $interventionExtension ?? $this->createMock(InterventionExtension::class),
            $activityPlanningExtension ?? $this->createMock(ActivityPlanningExtension::class),
            $userExtension ?? $this->createMock(UserCollectionExtension::class),
            $entrepriseExtension ?? $this->createMock(EntrepriseCollectionExtension::class),
            $tombstoneRepository ?? $this->createMock(TombstoneRecordRepository::class),
            $this->createMock(CategorieRisqueRepository::class),
            $this->createMock(InstallationEquipementRepository::class),
            $this->createMock(SiteRepository::class),
        );
    }
}
