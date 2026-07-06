<?php

declare(strict_types=1);

namespace App\Domain\PlanPrevention\Enum;

enum DecisionHse: string
{
    case VALIDE = 'VALIDE';
    case REFUSE = 'REFUSE';
}
