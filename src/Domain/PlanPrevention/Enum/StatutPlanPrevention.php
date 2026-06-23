<?php

declare(strict_types=1);

namespace App\Domain\PlanPrevention\Enum;

enum StatutPlanPrevention: string
{
    case BROUILLON = 'BROUILLON';
    case SOUMIS    = 'SOUMIS';
    case VALIDE    = 'VALIDE';
    case REJETE    = 'REJETE';
}
