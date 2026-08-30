<?php

declare(strict_types=1);

namespace App\Tests\Unit\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use App\Domain\Intervention\Entity\Intervention;
use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Doctrine\Extension\PermitTravailGroupeFilterExtension;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class PermitTravailGroupeFilterExtensionTest extends TestCase
{
    private PermitTravailGroupeFilterExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new PermitTravailGroupeFilterExtension();
    }

    public function testAddsJoinsAndWhereClauseWhenGroupeIdIsProvided(): void
    {
        $groupeId = '01a049da-13d7-7a8b-9526-29c841933d0e';
        $queryBuilder = $this->buildQueryBuilder();

        $this->extension->applyToCollection(
            $queryBuilder,
            new QueryNameGenerator(),
            PermitTravail::class,
            null,
            ['filters' => ['groupeId' => $groupeId]],
        );

        $dql = $queryBuilder->getDQL();
        $this->assertStringContainsString('p.groupeAsGeneral', $dql);
        $this->assertStringContainsString('p.groupeAsSpecialise', $dql);
        $this->assertStringContainsString('.id =', $dql);

        $parameter = $queryBuilder->getParameters()->first();
        $this->assertInstanceOf(Uuid::class, $parameter->getValue());
        $this->assertSame($groupeId, (string) $parameter->getValue());
    }

    public function testDoesNothingWhenGroupeIdIsAbsent(): void
    {
        $queryBuilder = $this->buildQueryBuilder();
        $originalDql = $queryBuilder->getDQL();

        $this->extension->applyToCollection(
            $queryBuilder,
            new QueryNameGenerator(),
            PermitTravail::class,
            null,
            ['filters' => []],
        );

        $this->assertSame($originalDql, $queryBuilder->getDQL());
        $this->assertCount(0, $queryBuilder->getParameters());
    }

    public function testDoesNothingWhenNoFiltersContextAtAll(): void
    {
        $queryBuilder = $this->buildQueryBuilder();
        $originalDql = $queryBuilder->getDQL();

        $this->extension->applyToCollection(
            $queryBuilder,
            new QueryNameGenerator(),
            PermitTravail::class,
            null,
            [],
        );

        $this->assertSame($originalDql, $queryBuilder->getDQL());
    }

    public function testMalformedGroupeIdNeverThrowsAndForcesEmptyResult(): void
    {
        $queryBuilder = $this->buildQueryBuilder();

        $this->extension->applyToCollection(
            $queryBuilder,
            new QueryNameGenerator(),
            PermitTravail::class,
            null,
            ['filters' => ['groupeId' => 'not-a-uuid']],
        );

        $this->assertStringContainsString('1 = 0', $queryBuilder->getDQL());
        // Aucun JOIN inutile ajouté quand l'UUID est invalide.
        $this->assertStringNotContainsString('groupeAsGeneral', $queryBuilder->getDQL());
    }

    public function testIgnoresUnrelatedResourceClass(): void
    {
        $queryBuilder = $this->buildQueryBuilder();
        $originalDql = $queryBuilder->getDQL();

        $this->extension->applyToCollection(
            $queryBuilder,
            new QueryNameGenerator(),
            Intervention::class,
            null,
            ['filters' => ['groupeId' => '01a049da-13d7-7a8b-9526-29c841933d0e']],
        );

        $this->assertSame($originalDql, $queryBuilder->getDQL());
    }

    private function buildQueryBuilder(): QueryBuilder
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getExpressionBuilder')->willReturn(new Expr());

        $queryBuilder = new QueryBuilder($entityManager);
        $queryBuilder->select('p')->from(PermitTravail::class, 'p');

        return $queryBuilder;
    }
}
