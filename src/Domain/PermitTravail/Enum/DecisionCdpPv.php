<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Enum;

enum DecisionCdpPv: string
{
    case VALIDE = 'VALIDE';
    case REFUSE = 'REFUSE';
}
