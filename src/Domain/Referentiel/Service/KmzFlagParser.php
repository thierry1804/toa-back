<?php

declare(strict_types=1);

namespace App\Domain\Referentiel\Service;

final class KmzFlagParser
{
    public static function parse(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'oui', 'yes', 'x'], true);
    }
}
