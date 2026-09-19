<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Service;

use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Enum\ProcessusPermitTravail;
use App\Domain\PermitTravail\Enum\TypeDocumentPermitTravail;
use App\Domain\PermitTravail\Enum\TypePermitTravail;
use App\Domain\Referentiel\Repository\SiteRepository;

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

    /**
     * Documents de clôture, exigés à la clôture manuelle d'un permis —
     * cf. tableau de la recette interne, étape "Fin" / "Validation Fin".
     *
     * @var array<string, TypeDocumentPermitTravail[]>
     */
    private const CLOTURE_MATRIX = [
        self::NOUVEAU_SITE => [
            TypeDocumentPermitTravail::PV_CLOTURE_ENVIRONNEMENT,
            TypeDocumentPermitTravail::PV_FIN_TRAVAUX,
            TypeDocumentPermitTravail::PHOTO_AVANT_TRAVAUX,
            TypeDocumentPermitTravail::PHOTO_APRES_TRAVAUX,
        ],
        self::AUTRES => [
            TypeDocumentPermitTravail::PHOTO_PROPRETE_SITE,
            TypeDocumentPermitTravail::PV_FIN_TRAVAUX,
            TypeDocumentPermitTravail::PHOTO_AVANT_TRAVAUX,
            TypeDocumentPermitTravail::PHOTO_APRES_TRAVAUX,
        ],
    ];

    /**
     * Pièces environnementales exigées à la soumission du permis général, pour les
     * sites en aire protégée (APN / API).
     *
     * @var array<string, TypeDocumentPermitTravail[]>
     */
    private const ENV_SOUMISSION_MATRIX = [
        self::NOUVEAU_SITE => [
            TypeDocumentPermitTravail::ENV_TRI_DECHETS,
            TypeDocumentPermitTravail::ENV_DELIMITATION_SITE,
            TypeDocumentPermitTravail::ENV_ACCES_EXISTANT,
            TypeDocumentPermitTravail::ENV_AUTORISATION_CEF_DREDD,
            TypeDocumentPermitTravail::ENV_FICHE_TOOLBOX,
            TypeDocumentPermitTravail::ENV_INVENTAIRE_ESPECES,
        ],
        self::AUTRES => [
            TypeDocumentPermitTravail::ENV_MATERIELS_DEVERSEMENT,
            TypeDocumentPermitTravail::ENV_MOYENS_URGENCE_POLLUTION,
            TypeDocumentPermitTravail::ENV_PROPRETE_AVANT,
        ],
    ];

    /**
     * Pièces environnementales exigées à la clôture du permis général, pour les
     * sites en aire protégée (APN / API).
     *
     * @var array<string, TypeDocumentPermitTravail[]>
     */
    private const ENV_CLOTURE_MATRIX = [
        self::NOUVEAU_SITE => [
            TypeDocumentPermitTravail::ENV_PHOTO_GENERATEUR_SUPERSILENT,
            TypeDocumentPermitTravail::ENV_LUTTE_EROSION,
            TypeDocumentPermitTravail::ENV_REGISTRE_DECHETS,
            TypeDocumentPermitTravail::ENV_PROPRETE_SITE,
            TypeDocumentPermitTravail::ENV_REGISTRE_PLAINTES,
        ],
        self::AUTRES => [
            TypeDocumentPermitTravail::ENV_QUANTITE_DECHETS,
            TypeDocumentPermitTravail::ENV_ENLEVEMENT_DECHETS,
            TypeDocumentPermitTravail::ENV_PROPRETE_APRES,
        ],
    ];

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

    public function __construct(private readonly SiteRepository $siteRepository)
    {
    }

    /** @return TypeDocumentPermitTravail[] */
    public function getRequiredDocumentTypes(PermitTravail $permit): array
    {
        $isRenouvellement = $permit->getProcessus() === ProcessusPermitTravail::RENOUVELLEMENT ? 1 : 0;
        $required = self::MATRIX[$this->bucket($permit)][$permit->getType()?->value ?? ''][$isRenouvellement] ?? [];

        if ($permit->getType() === TypePermitTravail::GENERAL && $this->isApnApiSite($permit)) {
            $required = array_merge($required, self::ENV_SOUMISSION_MATRIX[$this->bucket($permit)]);
        }

        return $required;
    }

    public function isApnApiSite(PermitTravail $permit): bool
    {
        $codeSite = $permit->getCodeSite();
        if ($codeSite === null || $codeSite === '') {
            return false;
        }

        $site = $this->siteRepository->findByCodeSite($codeSite);

        return $site !== null && ($site->isApn() || $site->isApi());
    }

    /** @return TypeDocumentPermitTravail[] */
    public function findMissingDocumentTypes(PermitTravail $permit): array
    {
        return $this->findMissing($permit, $this->getRequiredDocumentTypes($permit));
    }

    /** @return TypeDocumentPermitTravail[] */
    public function getRequiredClotureDocumentTypes(PermitTravail $permit): array
    {
        $required = self::CLOTURE_MATRIX[$this->bucket($permit)];

        if ($permit->getType() === TypePermitTravail::GENERAL && $this->isApnApiSite($permit)) {
            $required = array_merge($required, self::ENV_CLOTURE_MATRIX[$this->bucket($permit)]);
        }

        return $required;
    }

    /** @return TypeDocumentPermitTravail[] */
    public function findMissingClotureDocumentTypes(PermitTravail $permit): array
    {
        return $this->findMissing($permit, $this->getRequiredClotureDocumentTypes($permit));
    }

    private function bucket(PermitTravail $permit): string
    {
        return $permit->getPlanProcess() === 'Nouveau site' ? self::NOUVEAU_SITE : self::AUTRES;
    }

    /**
     * @param TypeDocumentPermitTravail[] $required
     * @return TypeDocumentPermitTravail[]
     */
    private function findMissing(PermitTravail $permit, array $required): array
    {
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
