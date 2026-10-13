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
    case LISTE_VEHICULES     = 'LISTE_VEHICULES';

    // R-09 : photos obligatoires (processus « Nouveau site ») des listes installations/équipements.
    case PHOTOS_INSTALLATIONS = 'PHOTOS_INSTALLATIONS';
    case PHOTOS_EQUIPEMENTS   = 'PHOTOS_EQUIPEMENTS';
}
