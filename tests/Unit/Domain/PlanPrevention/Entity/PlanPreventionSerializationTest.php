<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\PlanPrevention\Entity;

use App\Domain\PlanPrevention\Entity\PlanPrevention;
use PHPUnit\Framework\TestCase;

class PlanPreventionSerializationTest extends TestCase
{
    /** Le plan est placé dans le contexte des emails qui transitent par Messenger (sérialisation). */
    public function testPlanWithPendingApnApiLoaderIsSerializable(): void
    {
        $sites = [['codeSite' => 'ABC01', 'nomSite' => 'Ambatolampy', 'apn' => true, 'api' => false]];

        $plan = (new PlanPrevention())->setCodeSite('ABC01');
        $plan->setSitesApnApiLoader(static fn (): array => $sites);

        $copy = unserialize(serialize($plan));

        $this->assertInstanceOf(PlanPrevention::class, $copy);
        $this->assertSame('ABC01', $copy->getCodeSite());
        $this->assertSame($sites, $copy->getSitesApnApi());
        $this->assertTrue($copy->getHasApnApiSite());
    }
}
