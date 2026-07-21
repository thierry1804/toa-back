<?php

declare(strict_types=1);

namespace App\Domain\Intervention\Enum;

enum StatutIntervention: string
{
    case EN_PREPARATION       = 'EN_PREPARATION';
    case EVALUATION_COMPLETE  = 'EVALUATION_COMPLETE';
    case EN_COURS             = 'EN_COURS';
    case TERMINEE             = 'TERMINEE';
}
