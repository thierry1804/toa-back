<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Enum;

enum ProcessusPermitTravail: string
{
    case NOUVEAU_SITE   = 'NOUVEAU_SITE';
    case MAINTENANCE    = 'MAINTENANCE';
    case RENOUVELLEMENT = 'RENOUVELLEMENT';
}
