<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Service;

use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Enum\ProcessusPermitTravail;
use App\Domain\PermitTravail\Enum\TypeDocumentPermitTravail;
use App\Domain\PermitTravail\Enum\TypePermitTravail;

/**
 * Résout la liste des documents obligatoires pour un permis de travail, en
 * fonction du processus du plan de prévention rattaché (Nouveau site vs les
 * 4 autres processus regroupés), du type de permis et du fait qu'il s'agisse
 * d'une première demande ou d'un renouvellement — cf. tableau de la recette
 * interne (section "Demande de permis").
 */
final class PermitDocumentRequirementResolver
{
    private const NOUVEAU_SITE = 'NOUVEAU_SITE';
    private const AUTRES       = 'AUTRES';

    /** @var array<string, array<string, array<int, TypeDocumentPermitTravail[]>>> */
    private const MATRIX = [
        self::NOUVEAU_SITE => [
            TypePermitTravail::GENERAL->value => [
                0 => [
                    TypeDocumentPermitTravail::TROUSSE_SECOURS,
                    TypeDocumentPermitTravail::PV_SENSIBILISATION_HSE,
                    TypeDocumentPermitTravail::PHOTO_EPI_BASE,
                    TypeDocumentPermitTravail::PV_KICKOFF_AUTORITES,
                    TypeDocumentPermitTravail::PV_ENVIRONNEMENT,
                ],
                1 => [
                    TypeDocumentPermitTravail::PHOTO_AVANCEMENT_CHANTIER,
                    TypeDocumentPermitTravail::PHOTO_EPI_BASE,
                ],
            ],
            TypePermitTravail::HAUTEUR->value => [
                0 => [
                    TypeDocumentPermitTravail::PLAN_SAUVETAGE_HAUTEUR,
                    TypeDocumentPermitTravail::KIT_SAUVETAGE_HAUTEUR,
                    TypeDocumentPermitTravail::HARNAIS_SECURITE,
                    TypeDocumentPermitTravail::PIECE_IDENTITE_HABILITE,
                    TypeDocumentPermitTravail::TROUSSE_SECOURS,
                    TypeDocumentPermitTravail::MESURE_VITESSE_VENT,
                ],
                1 => [
                    TypeDocumentPermitTravail::PHOTO_AVANCEMENT_CHANTIER,
                    TypeDocumentPermitTravail::PHOTO_EPI_SPECIFIQUE,
                ],
            ],
            TypePermitTravail::ELECTRIQUE->value => [
                0 => [],
                1 => [],
            ],
        ],
        self::AUTRES => [
            TypePermitTravail::GENERAL->value => [
                0 => [
                    TypeDocumentPermitTravail::TROUSSE_SECOURS,
                    TypeDocumentPermitTravail::PHOTO_EPI_BASE,
                    TypeDocumentPermitTravail::PV_SENSIBILISATION_HSE,
                ],
                1 => [
                    TypeDocumentPermitTravail::PHOTO_AVANCEMENT_CHANTIER,
                    TypeDocumentPermitTravail::PHOTO_EPI_BASE,
                ],
            ],
            TypePermitTravail::HAUTEUR->value => [
                0 => [
                    TypeDocumentPermitTravail::PLAN_SAUVETAGE_HAUTEUR,
                    TypeDocumentPermitTravail::KIT_SAUVETAGE_HAUTEUR,
                    TypeDocumentPermitTravail::HARNAIS_SECURITE,
                    TypeDocumentPermitTravail::PIECE_IDENTITE_HABILITE,
                    TypeDocumentPermitTravail::TROUSSE_SECOURS,
                    TypeDocumentPermitTravail::MESURE_VITESSE_VENT,
                ],
                1 => [],
            ],
            TypePermitTravail::ELECTRIQUE->value => [
                0 => [
                    TypeDocumentPermitTravail::PIECE_IDENTITE_HABILITE,
                    TypeDocumentPermitTravail::PHOTO_EQUIPEMENTS_ISOLANTS,
                    TypeDocumentPermitTravail::TROUSSE_SECOURS,
                ],
                1 => [
                    TypeDocumentPermitTravail::PHOTO_AVANCEMENT_CHANTIER,
                    TypeDocumentPermitTravail::PHOTO_EPI_SPECIFIQUE,
                ],
            ],
        ],
    ];

    /** @return TypeDocumentPermitTravail[] */
    public function getRequiredDocumentTypes(PermitTravail $permit): array
    {
        $bucket = $permit->getPlanProcess() === 'Nouveau site' ? self::NOUVEAU_SITE : self::AUTRES;
        $isRenouvellement = $permit->getProcessus() === ProcessusPermitTravail::RENOUVELLEMENT ? 1 : 0;

        return self::MATRIX[$bucket][$permit->getType()?->value ?? ''][$isRenouvellement] ?? [];
    }

    /** @return TypeDocumentPermitTravail[] */
    public function findMissingDocumentTypes(PermitTravail $permit): array
    {
        $required = $this->getRequiredDocumentTypes($permit);

        $presentTypes = array_map(
            static fn ($doc) => $doc->getType(),
            $permit->getDocuments()->toArray(),
        );

        return array_values(array_filter(
            $required,
            static fn (TypeDocumentPermitTravail $req) => !in_array($req, $presentTypes, true),
        ));
    }
}
