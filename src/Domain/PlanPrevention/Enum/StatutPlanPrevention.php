<?php

declare(strict_types=1);

namespace App\Domain\PlanPrevention\Enum;

enum StatutPlanPrevention: string
{
    case BROUILLON              = 'BROUILLON';
    case SOUMIS                 = 'SOUMIS';
    case VALIDE                 = 'VALIDE';
    case REJETE                 = 'REJETE';
    case EN_ATTENTE             = 'EN_ATTENTE';
    case ENVOYE                 = 'ENVOYE';
    case EN_COURS_DE_VALIDATION = 'EN_COURS_DE_VALIDATION';
    case EXAMINE                = 'EXAMINE';
    case VALIDE_HSE             = 'VALIDE_HSE';
    case REFUSE_HSE             = 'REFUSE_HSE';
}
