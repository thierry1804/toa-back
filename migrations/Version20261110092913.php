<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sépare "obligatoire" (document exigé) de "autorise la case N/A" (peut être
 * contourné via N/A même quand il est obligatoire). Corrige au passage un
 * correctif précédent trop grossier : les 16 pièces passées à tort en
 * `obligatoire = false` redeviennent obligatoires, avec `autorise_na = true`
 * à la place — elles restent donc exigées, mais skippables au cas par cas,
 * comme le terrain le faisait déjà avant ce référentiel (preuve en base :
 * documents historiquement marqués non applicables malgré la matrice figée).
 */
final class Version20261110092913 extends AbstractMigration
{
    private const AUTORISE_NA_CODES = [
        'TROUSSE_SECOURS', 'PHOTO_EPI_BASE', 'PV_SENSIBILISATION_HSE', 'PIECE_IDENTITE_HABILITE',
        'PV_ENVIRONNEMENT', 'PV_KICKOFF_AUTORITES', 'MESURE_VITESSE_VENT', 'PHOTO_EQUIPEMENTS_ISOLANTS',
        'HARNAIS_SECURITE', 'KIT_SAUVETAGE_HAUTEUR', 'PHOTO_APRES_TRAVAUX', 'PHOTO_AVANT_TRAVAUX',
        'PV_FIN_TRAVAUX', 'PHOTO_EPI_SPECIFIQUE', 'PHOTO_PROPRETE_SITE', 'PLAN_SAUVETAGE_HAUTEUR',
    ];

    public function getDescription(): string
    {
        return 'Ajoute document_permis_regle.autorise_na et corrige les 16 règles basculées par erreur en non-obligatoire';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document_permis_regle ADD autorise_na BOOLEAN DEFAULT false NOT NULL');

        $codes = "'" . implode("','", self::AUTORISE_NA_CODES) . "'";
        $this->addSql("UPDATE document_permis_regle SET obligatoire = true, autorise_na = true WHERE code IN ($codes)");
        $this->addSql("UPDATE document_permis_regle SET autorise_na = true WHERE code = 'PHOTO_DANGERS'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document_permis_regle DROP autorise_na');

        $codes = "'" . implode("','", self::AUTORISE_NA_CODES) . "'";
        $this->addSql("UPDATE document_permis_regle SET obligatoire = false WHERE code IN ($codes)");
    }
}
