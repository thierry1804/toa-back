<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\PlanPrevention\Service;

use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Service\ApnApiSiteResolver;
use App\Domain\Referentiel\Entity\Site;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

class ApnApiSiteResolverTest extends TestCase
{
    public function testReferentielLookupIsCaseInsensitive(): void
    {
        $site = (new Site())->setCodeSite('ABC01')->setNomSite('Ambatolampy')->setApn(true);

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([$site]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $queryBuilder->expects($this->once())->method('where')
            ->with($this->stringContains('LOWER(s.codeSite)'))
            ->willReturnSelf();
        $queryBuilder->expects($this->once())->method('setParameter')
            ->with('codes', ['abc01'])
            ->willReturnSelf();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('createQueryBuilder')->willReturn($queryBuilder);

        $plan = (new PlanPrevention())->setCodeSite('ABC01');

        $result = (new ApnApiSiteResolver($entityManager))->resolveForPlan($plan);

        $this->assertSame([['codeSite' => 'ABC01', 'nomSite' => 'Ambatolampy', 'apn' => true, 'api' => false]], $result);
    }
}
