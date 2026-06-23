<?php

declare(strict_types=1);

namespace App\Domain\PlanPrevention\Enum;

enum TypeDocumentPrevention: string
{
    case PLAN_URGENCE        = 'PLAN_URGENCE';
    case FDS                 = 'FDS';
    case LISTE_INTERVENANTS  = 'LISTE_INTERVENANTS';
    case ATTESTATION_HSE     = 'ATTESTATION_HSE';
    case FICHE_CONFORMITE    = 'FICHE_CONFORMITE';
}
