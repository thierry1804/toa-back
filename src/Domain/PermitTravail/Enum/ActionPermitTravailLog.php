<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Enum;

enum ActionPermitTravailLog: string
{
    case CONSULTE            = 'CONSULTE';
    case DOCUMENT_TELECHARGE = 'DOCUMENT_TELECHARGE';
    case STATUT_CHANGE       = 'STATUT_CHANGE';
    case PDF_GENERE          = 'PDF_GENERE';
}
