<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Enum;

enum TypePermitTravail: string
{
    case GENERAL    = 'GENERAL';
    case ELECTRIQUE = 'ELECTRIQUE';
    case HAUTEUR    = 'HAUTEUR';
}
