<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Enum;

enum StatutPermitTravail: string
{
    case BROUILLON  = 'BROUILLON';
    case SOUMIS     = 'SOUMIS';
    case EN_COURS   = 'EN_COURS';
    case VALIDE     = 'VALIDE';
    case REJETE     = 'REJETE';
    case VALIDE_HSE = 'VALIDE_HSE';
    case REFUSE_HSE = 'REFUSE_HSE';
    case CLOTURE    = 'CLOTURE';
    case PV_VALIDE  = 'PV_VALIDE';
    case PV_REFUSE  = 'PV_REFUSE';
}
