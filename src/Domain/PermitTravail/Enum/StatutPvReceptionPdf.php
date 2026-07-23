<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Enum;

enum StatutPvReceptionPdf: string
{
    case EN_COURS = 'EN_COURS';
    case GENERE   = 'GENERE';
    case ERREUR   = 'ERREUR';
}
