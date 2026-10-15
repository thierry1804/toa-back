<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\PermitTravail\Entity;

use App\Domain\PermitTravail\Entity\PermitTravail;
use PHPUnit\Framework\TestCase;

/** R-24 : la référence d\x27un renouvellement reprend celle de l\x27origine, suffixée R1, R2… */
class PermitTravailRenouvellementTest extends TestCase
{
    public function testReferenceOrigineIgnoreLeSuffixeDeRenouvellement(): void
    {
        $permit = (new PermitTravail())->setReference(\x272026/PTW-TOA-0012-R2\x27);

        $this->assertSame(\x272026/PTW-TOA-0012\x27, $permit->getReferenceOrigine());
    }

    public function testReferenceOrigineInchangeePourUnPermisDOrigine(): void
    {
        $permit = (new PermitTravail())->setReference(\x272026/PTW-TOA-0012\x27);

        $this->assertSame(\x272026/PTW-TOA-0012\x27, $permit->getReferenceOrigine());
    }
}
