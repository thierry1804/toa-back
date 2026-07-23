<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Enum;

enum TypeCloturePerm: string
{
    case MANUELLE    = 'MANUELLE';
    case AUTOMATIQUE = 'AUTOMATIQUE';
}
