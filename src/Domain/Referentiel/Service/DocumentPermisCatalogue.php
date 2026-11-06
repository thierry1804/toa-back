<?php

declare(strict_types=1);

namespace App\Domain\Referentiel\Service;

/**
 * Liste de départ, reprise du code : les pièces de la matrice sont
 * obligatoires, les photos des dangers sont facultatives.
 */
final class DocumentPermisCatalogue
{
    public const MOMENT_SOUMISSION = 'SOUMISSION';
    public const MOMENT_CLOTURE = 'CLOTURE';
    public const MOMENT_DANGERS = 'DANGERS';
    public const TOUS = 'TOUS';
    public const TOUTES = 'TOUTES';
    public const PREMIERE = 'PREMIERE';
    public const RENOUVELLEMENT = 'RENOUVELLEMENT';
    public const NOUVEAU_SITE = 'NOUVEAU_SITE';
    public const AUTRES = 'AUTRES';

    /** @var array<string, string> */
    private const LIBELLES = [
        'TROUSSE_SECOURS' => 'Trousse de secours',
        'PV_SENSIBILISATION_HSE' => 'PV de sensibilisation HSE',
        'PHOTO_EPI_BASE' => 'Photo des intervenants en EPI de base',
        'PV_KICKOFF_AUTORITES' => 'PV kick-off signé par les autorités locales',
        'PV_ENVIRONNEMENT' => 'PV Environnement',
        'PHOTO_AVANCEMENT_CHANTIER' => "Photo d'avancement du chantier",
        'PLAN_SAUVETAGE_HAUTEUR' => 'Plan de sauvetage en hauteur',
        'KIT_SAUVETAGE_HAUTEUR' => 'Kit de sauvetage en hauteur',
        'HARNAIS_SECURITE' => 'Harnais de sécurité complet',
        'PIECE_IDENTITE_HABILITE' => "Pièce d'identité de la personne habilitée",
        'MESURE_VITESSE_VENT' => 'Mesure de la vitesse du vent',
        'PHOTO_EPI_SPECIFIQUE' => 'Photo des intervenants en EPI spécifique',
        'PHOTO_EQUIPEMENTS_ISOLANTS' => 'Photo des équipements isolants',
        'PV_CLOTURE_ENVIRONNEMENT' => 'PV clôture Environnement',
        'PHOTO_PROPRETE_SITE' => 'Photo propreté du site et/ou remise en état',
        'PHOTO_AVANT_TRAVAUX' => 'Photo avant travaux',
        'PHOTO_DANGERS' => 'Photos des dangers',
        'ENV_TRI_DECHETS' => 'Mise en place du tri des déchets',
        'ENV_DELIMITATION_SITE' => 'Délimitation du site avant travaux',
        'ENV_ACCES_EXISTANT' => 'Accès déjà existant sur site avant chantier',
        'ENV_AUTORISATION_CEF_DREDD' => "Autorisation CEF ou DREDD pour l'ouverture de piste",
        'ENV_FICHE_TOOLBOX' => 'Fiche toolbox de sensibilisation (maîtrise sonore, chasse, faune, flore)',
        'ENV_INVENTAIRE_ESPECES' => 'Inventaire et suivi des espèces cibles de conservation dans le périmètre du site',
        'ENV_MATERIELS_DEVERSEMENT' => 'Matériels utilisés pour la maîtrise des déversements',
        'ENV_MOYENS_URGENCE_POLLUTION' => "Moyens de maîtrise en cas d'urgence liée à une pollution",
        'ENV_PROPRETE_AVANT' => 'Propreté du site avant intervention',
        'ENV_PHOTO_GENERATEUR_SUPERSILENT' => 'Photo du générateur supersilent sur site',
        'ENV_LUTTE_EROSION' => "Lutte contre l'érosion (caniveaux de drainage, revégétalisation)",
        'ENV_REGISTRE_DECHETS' => 'Registre des déchets',
        'ENV_PROPRETE_SITE' => 'Propreté du site',
        'ENV_REGISTRE_PLAINTES' => 'Cahier de registre des plaintes (internes et externes)',
        'ENV_QUANTITE_DECHETS' => 'Quantité de déchets générés par type',
        'ENV_ENLEVEMENT_DECHETS' => 'Enlèvement des déchets',
        'ENV_PROPRETE_APRES' => 'Propreté du site après intervention',
    ];

    /**
     * @return list<array{code: string, libelle: string, moment: string, processus: string, typePermis: string, demande: string, apnApiUniquement: bool, obligatoire: bool, ordre: int}>
     */
    public static function definitions(): array
    {
        $rows = [];
        $push = static function (array $codes, string $moment, string $processus, string $typePermis, string $demande, bool $apn = false) use (&$rows): void {
            foreach (array_values($codes) as $ordre => $code) {
                $rows[] = [
                    'code' => $code,
                    'libelle' => self::LIBELLES[$code],
                    'moment' => $moment,
                    'processus' => $processus,
                    'typePermis' => $typePermis,
                    'demande' => $demande,
                    'apnApiUniquement' => $apn,
                    'obligatoire' => true,
                    'ordre' => $ordre,
                ];
            }
        };

        $hauteur = ['PLAN_SAUVETAGE_HAUTEUR', 'KIT_SAUVETAGE_HAUTEUR', 'HARNAIS_SECURITE', 'PIECE_IDENTITE_HABILITE', 'TROUSSE_SECOURS', 'MESURE_VITESSE_VENT'];
        $electrique = ['PIECE_IDENTITE_HABILITE', 'PHOTO_EQUIPEMENTS_ISOLANTS', 'TROUSSE_SECOURS'];
        $renouvellementSpecial = ['PHOTO_AVANCEMENT_CHANTIER', 'PHOTO_EPI_SPECIFIQUE'];

        $push(['TROUSSE_SECOURS', 'PV_SENSIBILISATION_HSE', 'PHOTO_EPI_BASE', 'PV_KICKOFF_AUTORITES', 'PV_ENVIRONNEMENT'], self::MOMENT_SOUMISSION, self::NOUVEAU_SITE, 'GENERAL', self::PREMIERE);
        $push(['PHOTO_AVANCEMENT_CHANTIER', 'PHOTO_EPI_BASE'], self::MOMENT_SOUMISSION, self::NOUVEAU_SITE, 'GENERAL', self::RENOUVELLEMENT);
        $push($hauteur, self::MOMENT_SOUMISSION, self::NOUVEAU_SITE, 'HAUTEUR', self::PREMIERE);
        $push($renouvellementSpecial, self::MOMENT_SOUMISSION, self::NOUVEAU_SITE, 'HAUTEUR', self::RENOUVELLEMENT);
        $push($electrique, self::MOMENT_SOUMISSION, self::NOUVEAU_SITE, 'ELECTRIQUE', self::PREMIERE);
        $push($renouvellementSpecial, self::MOMENT_SOUMISSION, self::NOUVEAU_SITE, 'ELECTRIQUE', self::RENOUVELLEMENT);

        $push(['TROUSSE_SECOURS', 'PHOTO_EPI_BASE', 'PV_SENSIBILISATION_HSE'], self::MOMENT_SOUMISSION, self::AUTRES, 'GENERAL', self::PREMIERE);
        $push(['PHOTO_AVANCEMENT_CHANTIER', 'PHOTO_EPI_BASE'], self::MOMENT_SOUMISSION, self::AUTRES, 'GENERAL', self::RENOUVELLEMENT);
        $push($hauteur, self::MOMENT_SOUMISSION, self::AUTRES, 'HAUTEUR', self::PREMIERE);
        $push($electrique, self::MOMENT_SOUMISSION, self::AUTRES, 'ELECTRIQUE', self::PREMIERE);
        $push($renouvellementSpecial, self::MOMENT_SOUMISSION, self::AUTRES, 'ELECTRIQUE', self::RENOUVELLEMENT);

        $push(['ENV_TRI_DECHETS', 'ENV_DELIMITATION_SITE', 'ENV_ACCES_EXISTANT', 'ENV_AUTORISATION_CEF_DREDD', 'ENV_FICHE_TOOLBOX', 'ENV_INVENTAIRE_ESPECES'], self::MOMENT_SOUMISSION, self::NOUVEAU_SITE, 'GENERAL', self::TOUTES, true);
        $push(['ENV_MATERIELS_DEVERSEMENT', 'ENV_MOYENS_URGENCE_POLLUTION', 'ENV_PROPRETE_AVANT'], self::MOMENT_SOUMISSION, self::AUTRES, 'GENERAL', self::TOUTES, true);

        $push(['PV_CLOTURE_ENVIRONNEMENT', 'PHOTO_AVANT_TRAVAUX'], self::MOMENT_CLOTURE, self::NOUVEAU_SITE, self::TOUS, self::TOUTES);
        $push(['PHOTO_PROPRETE_SITE', 'PHOTO_AVANT_TRAVAUX'], self::MOMENT_CLOTURE, self::AUTRES, self::TOUS, self::TOUTES);
        $push(['ENV_PHOTO_GENERATEUR_SUPERSILENT', 'ENV_LUTTE_EROSION', 'ENV_REGISTRE_DECHETS', 'ENV_PROPRETE_SITE', 'ENV_REGISTRE_PLAINTES'], self::MOMENT_CLOTURE, self::NOUVEAU_SITE, 'GENERAL', self::TOUTES, true);
        $push(['ENV_QUANTITE_DECHETS', 'ENV_ENLEVEMENT_DECHETS', 'ENV_PROPRETE_APRES'], self::MOMENT_CLOTURE, self::AUTRES, 'GENERAL', self::TOUTES, true);

        $rows[] = [
            'code' => 'PHOTO_DANGERS',
            'libelle' => self::LIBELLES['PHOTO_DANGERS'],
            'moment' => self::MOMENT_DANGERS,
            'processus' => self::TOUS,
            'typePermis' => self::TOUS,
            'demande' => self::TOUTES,
            'apnApiUniquement' => false,
            'obligatoire' => false,
            'ordre' => 0,
        ];

        return $rows;
    }
}
