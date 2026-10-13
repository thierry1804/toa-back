<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Intervention\Repository;

use App\Domain\Intervention\Repository\InterventionRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for InterventionRepository::fetchLatestAvancementByIntervention(),
 * the private helper behind Bug B's fix (B1/B2): the dashboard and
 * stats-journalieres endpoints must expose the MOST RECENT suivi journalier's
 * avancementPourcentage per intervention, not an average.
 *
 * No PostgreSQL test database is reachable from this environment, so the
 * "DISTINCT ON (intervention_id) ... ORDER BY intervention_id, date DESC,
 * created_at DESC" native SQL itself cannot be executed here. These tests
 * instead mock Connection::fetchAllAssociative() to stand in for what that
 * query would return (already deduplicated to the latest row per
 * intervention_id, as DISTINCT ON guarantees), and verify the PHP-side
 * mapping/merging logic: no cross-intervention contamination, and interventions
 * absent from the result are simply absent from the map (callers default to 0).
 *
 * The exact SQL to verify manually against the real database is:
 *
 *   SELECT DISTINCT ON (intervention_id) intervention_id, avancement_pourcentage
 *   FROM suivi_journalier
 *   ORDER BY intervention_id, date DESC, created_at DESC
 */
class InterventionRepositoryLatestAvancementTest extends TestCase
{
    public function testMapsEachInterventionToItsOwnLatestAvancement(): void
    {
        $repository = $this->buildRepositoryReturning([
            ['intervention_id' => 'intervention-1', 'avancement_pourcentage' => '21'],
            ['intervention_id' => 'intervention-2', 'avancement_pourcentage' => '5'],
        ]);

        $result = $this->invokeFetchLatest($repository, ['intervention-1', 'intervention-2']);

        $this->assertSame(
            ['intervention-1' => 21, 'intervention-2' => 5],
            $result,
        );
    }

    public function testDoesNotCrossContaminateBetweenInterventions(): void
    {
        $repository = $this->buildRepositoryReturning([
            ['intervention_id' => 'intervention-a', 'avancement_pourcentage' => '2'],
            ['intervention_id' => 'intervention-b', 'avancement_pourcentage' => '99'],
            ['intervention_id' => 'intervention-c', 'avancement_pourcentage' => '40'],
        ]);

        $result = $this->invokeFetchLatest($repository, ['intervention-a', 'intervention-b', 'intervention-c']);

        $this->assertSame(2, $result['intervention-a']);
        $this->assertSame(99, $result['intervention-b']);
        $this->assertSame(40, $result['intervention-c']);
    }

    public function testOnlyKeepsEntriesForRequestedInterventionIds(): void
    {
        $repository = $this->buildRepositoryReturning([
            ['intervention_id' => 'wanted', 'avancement_pourcentage' => '30'],
            ['intervention_id' => 'not-requested', 'avancement_pourcentage' => '77'],
        ]);

        $result = $this->invokeFetchLatest($repository, ['wanted']);

        $this->assertSame(['wanted' => 30], $result);
    }

    public function testInterventionWithNoSuiviAtAllIsSimplyAbsentFromTheMap(): void
    {
        $repository = $this->buildRepositoryReturning([
            ['intervention_id' => 'has-suivis', 'avancement_pourcentage' => '12'],
        ]);

        $result = $this->invokeFetchLatest($repository, ['has-suivis', 'no-suivis-at-all']);

        $this->assertSame(['has-suivis' => 12], $result);
        $this->assertArrayNotHasKey('no-suivis-at-all', $result);
        // Callers (getDashboardData / getActiveInterventionsBySite) merge with
        // `$latestAvancements[...] ?? 0`, so an absent key means avancementMoyen = 0.
    }

    public function testReturnsEmptyArrayWithoutQueryingWhenNoInterventionIdsAreRequested(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllAssociative');

        $repository = $this->buildRepositoryWithConnection($connection);

        $result = $this->invokeFetchLatest($repository, []);

        $this->assertSame([], $result);
    }

    /**
     * @param array<int, array{intervention_id: string, avancement_pourcentage: string}> $rows
     */
    private function buildRepositoryReturning(array $rows): InterventionRepository
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($rows);

        return $this->buildRepositoryWithConnection($connection);
    }

    private function buildRepositoryWithConnection(Connection&MockObject $connection): InterventionRepository
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        /** @var InterventionRepository&MockObject $repository */
        $repository = $this->getMockBuilder(InterventionRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getEntityManager'])
            ->getMock();
        $repository->method('getEntityManager')->willReturn($entityManager);

        return $repository;
    }

    /**
     * @param string[] $interventionIds
     *
     * @return array<string, int>
     */
    private function invokeFetchLatest(InterventionRepository $repository, array $interventionIds): array
    {
        $method = new \ReflectionMethod(InterventionRepository::class, 'fetchLatestAvancementByIntervention');
        $method->setAccessible(true);

        /** @var array<string, int> $result */
        $result = $method->invoke($repository, $interventionIds);

        return $result;
    }
}
