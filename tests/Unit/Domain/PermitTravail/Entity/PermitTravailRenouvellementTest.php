<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\PermitTravail\Entity;

use App\Domain\PermitTravail\Entity\PermitTravail;
use PHPUnit\Framework\TestCase;

/** R-24 : la référence d'un renouvellement reprend celle de l'origine, suffixée R1, R2… */
class PermitTravailRenouvellementTest extends TestCase
{
    public function testReferenceOrigineIgnoreLeSuffixeDeRenouvellement(): void
    {
        $permit = (new PermitTravail())->setReference('2026/PTW-TOA-0012-R2');

        $this->assertSame('2026/PTW-TOA-0012', $permit->getReferenceOrigine());
    }

    public function testReferenceOrigineInchangeePourUnPermisDOrigine(): void
    {
        $permit = (new PermitTravail())->setReference('2026/PTW-TOA-0012');

        $this->assertSame('2026/PTW-TOA-0012', $permit->getReferenceOrigine());
    }
}
