<?php

declare(strict_types=1);

namespace App\Domain\Referentiel;

/**
 * Référentiel des processus (valeur de `typeIntervention` d\x27une planification et
 * d\x27un plan de prévention) auxquels un chef de projet TOA peut être rattaché (R-30).
 */
final class ProcessusReferentiel
{
    public const NOUVEAU_SITE   = \x27Nouveau site\x27;
    public const VIE_DE_RESEAU  = \x27Vie de Réseau\x27;
    public const INFRASTRUCTURE = \x27Infrastructure\x27;
    public const MAINTENANCE    = \x27Opération et Mainténance\x27;
    public const ENERGIE        = \x27Energie\x27;

    public const VALUES = [
        self::NOUVEAU_SITE,
        self::VIE_DE_RESEAU,
        self::INFRASTRUCTURE,
        self::MAINTENANCE,
        self::ENERGIE,
    ];

    private function __construct()
    {
    }
}
