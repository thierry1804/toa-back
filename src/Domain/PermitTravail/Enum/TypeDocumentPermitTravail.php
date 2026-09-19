<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Enum;

enum TypeDocumentPermitTravail: string
{
    case TROUSSE_SECOURS             = 'TROUSSE_SECOURS';
    case PV_SENSIBILISATION_HSE      = 'PV_SENSIBILISATION_HSE';
    case PHOTO_EPI_BASE              = 'PHOTO_EPI_BASE';
    case PV_KICKOFF_AUTORITES        = 'PV_KICKOFF_AUTORITES';
    case PV_ENVIRONNEMENT            = 'PV_ENVIRONNEMENT';
    case PHOTO_AVANCEMENT_CHANTIER   = 'PHOTO_AVANCEMENT_CHANTIER';
    case PLAN_SAUVETAGE_HAUTEUR      = 'PLAN_SAUVETAGE_HAUTEUR';
    case KIT_SAUVETAGE_HAUTEUR       = 'KIT_SAUVETAGE_HAUTEUR';
    case HARNAIS_SECURITE            = 'HARNAIS_SECURITE';
    case PIECE_IDENTITE_HABILITE     = 'PIECE_IDENTITE_HABILITE';
    case MESURE_VITESSE_VENT         = 'MESURE_VITESSE_VENT';
    case PHOTO_EPI_SPECIFIQUE        = 'PHOTO_EPI_SPECIFIQUE';
    case PHOTO_EQUIPEMENTS_ISOLANTS  = 'PHOTO_EQUIPEMENTS_ISOLANTS';
    case PV_CLOTURE_ENVIRONNEMENT    = 'PV_CLOTURE_ENVIRONNEMENT';
    case PV_FIN_TRAVAUX              = 'PV_FIN_TRAVAUX';
    case PHOTO_PROPRETE_SITE         = 'PHOTO_PROPRETE_SITE';
    case PHOTO_AVANT_TRAVAUX         = 'PHOTO_AVANT_TRAVAUX';
    case PHOTO_APRES_TRAVAUX         = 'PHOTO_APRES_TRAVAUX';

    // Pièces environnementales — sites APN / API — permis général (avant travaux), Nouveau site.
    case ENV_TRI_DECHETS               = 'ENV_TRI_DECHETS';
    case ENV_DELIMITATION_SITE         = 'ENV_DELIMITATION_SITE';
    case ENV_ACCES_EXISTANT            = 'ENV_ACCES_EXISTANT';
    case ENV_AUTORISATION_CEF_DREDD    = 'ENV_AUTORISATION_CEF_DREDD';
    case ENV_FICHE_TOOLBOX             = 'ENV_FICHE_TOOLBOX';
    case ENV_INVENTAIRE_ESPECES        = 'ENV_INVENTAIRE_ESPECES';

    // Permis général (avant travaux), autres processus.
    case ENV_MATERIELS_DEVERSEMENT     = 'ENV_MATERIELS_DEVERSEMENT';
    case ENV_MOYENS_URGENCE_POLLUTION  = 'ENV_MOYENS_URGENCE_POLLUTION';
    case ENV_PROPRETE_AVANT            = 'ENV_PROPRETE_AVANT';

    // Fin de travaux, Nouveau site.
    case ENV_PHOTO_GENERATEUR_SUPERSILENT = 'ENV_PHOTO_GENERATEUR_SUPERSILENT';
    case ENV_LUTTE_EROSION                = 'ENV_LUTTE_EROSION';
    case ENV_REGISTRE_DECHETS             = 'ENV_REGISTRE_DECHETS';
    case ENV_PROPRETE_SITE                = 'ENV_PROPRETE_SITE';
    case ENV_REGISTRE_PLAINTES            = 'ENV_REGISTRE_PLAINTES';

    // Fin de travaux, autres processus.
    case ENV_QUANTITE_DECHETS          = 'ENV_QUANTITE_DECHETS';
    case ENV_ENLEVEMENT_DECHETS        = 'ENV_ENLEVEMENT_DECHETS';
    case ENV_PROPRETE_APRES            = 'ENV_PROPRETE_APRES';

    /**
     * Documents déposables une fois le permis sorti du brouillon, au moment de
     * sa clôture manuelle.
     */
    public function isClotureType(): bool
    {
        return in_array($this, [
            self::PV_CLOTURE_ENVIRONNEMENT,
            self::PV_FIN_TRAVAUX,
            self::PHOTO_PROPRETE_SITE,
            self::PHOTO_AVANT_TRAVAUX,
            self::PHOTO_APRES_TRAVAUX,
            self::ENV_PHOTO_GENERATEUR_SUPERSILENT,
            self::ENV_LUTTE_EROSION,
            self::ENV_REGISTRE_DECHETS,
            self::ENV_PROPRETE_SITE,
            self::ENV_REGISTRE_PLAINTES,
            self::ENV_QUANTITE_DECHETS,
            self::ENV_ENLEVEMENT_DECHETS,
            self::ENV_PROPRETE_APRES,
        ], true);
    }
}
