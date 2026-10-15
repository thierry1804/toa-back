<?php

declare(strict_types=1);

namespace App\Domain\Referentiel;

/**
 * Référentiel des processus (valeur de `typeIntervention` d'une planification et
 * d'un plan de prévention) auxquels un chef de projet TOA peut être rattaché (R-30).
 */
final class ProcessusReferentiel
{
    public const NOUVEAU_SITE   = 'Nouveau site';
    public const VIE_DE_RESEAU  = 'Vie de Réseau';
    public const INFRASTRUCTURE = 'Infrastructure';
    public const MAINTENANCE    = 'Opération et Mainténance';
    public const ENERGIE        = 'Energie';

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
