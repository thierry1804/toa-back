<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Resynchronise le schéma avec le mapping : renommage d'index vers les noms générés par
 * Doctrine, retrait des anciens commentaires DC2Type et des DEFAULT que le mapping ne porte pas.
 * Aucune donnée n'est modifiée.
 */
final class Version20260919230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Resynchronise le schéma avec le mapping Doctrine (index, commentaires, defaults)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER INDEX entreprise_nom_key RENAME TO UNIQ_D19FA606C6E55B5');
        $this->addSql("COMMENT ON COLUMN decision_cdp_pv_reception.id IS ''");
        $this->addSql("COMMENT ON COLUMN decision_cdp_pv_reception.permit_travail_id IS ''");
        $this->addSql("COMMENT ON COLUMN decision_cdp_pv_reception.decided_at IS ''");
        $this->addSql('ALTER INDEX idx_cdp_pv_permit RENAME TO IDX_BA6050FB33A6AE10');
        $this->addSql('ALTER INDEX idx_cdp_pv_decide_par RENAME TO IDX_BA6050FB2E54F2F1');
        $this->addSql('ALTER TABLE kpi_intervention ALTER nb_permis_clotures_valides DROP DEFAULT');
        $this->addSql('ALTER TABLE kpi_intervention ALTER nb_permis_clotures_total DROP DEFAULT');
        $this->addSql('ALTER TABLE kpi_intervention ALTER taux_cloture DROP DEFAULT');
        $this->addSql('ALTER TABLE kpi_intervention ALTER delai_moyen_validation_cdp DROP DEFAULT');
        $this->addSql("COMMENT ON COLUMN kpi_intervention.id IS ''");
        $this->addSql("COMMENT ON COLUMN kpi_intervention.updated_at IS ''");
        $this->addSql('ALTER INDEX uniq_cloture_permit_pt RENAME TO UNIQ_1A4E50C533A6AE10');
        $this->addSql('ALTER INDEX idx_cloture_permit_by RENAME TO IDX_1A4E50C5B1498889');
        $this->addSql('ALTER INDEX uniq_pv_reception_pt RENAME TO UNIQ_89AD58E333A6AE10');
        $this->addSql('ALTER INDEX idx_pv_reception_by RENAME TO IDX_89AD58E39DEC084D');
        $this->addSql("COMMENT ON COLUMN categorie_risque.id IS ''");
        $this->addSql("COMMENT ON COLUMN categorie_risque.created_at IS ''");
        $this->addSql("COMMENT ON COLUMN categorie_risque.updated_at IS ''");
        $this->addSql('ALTER INDEX uniq_categorie_risque_nom RENAME TO UNIQ_A605A71D6C6E55B5');
        $this->addSql('ALTER TABLE site ALTER latitude DROP DEFAULT');
        $this->addSql('ALTER TABLE site ALTER longitude DROP DEFAULT');
        $this->addSql('ALTER TABLE site ALTER source_kmz DROP DEFAULT');
        $this->addSql("COMMENT ON COLUMN site.id IS ''");
        $this->addSql('ALTER INDEX idx_site_import_kmz RENAME TO IDX_694309E453E00B04');
        $this->addSql("COMMENT ON COLUMN installation_equipement.id IS ''");
        $this->addSql("COMMENT ON COLUMN installation_equipement.created_at IS ''");
        $this->addSql("COMMENT ON COLUMN installation_equipement.updated_at IS ''");
        $this->addSql("COMMENT ON COLUMN installation_equipement.deleted_at IS ''");
        $this->addSql("COMMENT ON COLUMN tache_planifiee.id IS ''");
        $this->addSql("COMMENT ON COLUMN tache_planifiee.section_id IS ''");
        $this->addSql("COMMENT ON COLUMN section_planifiee.id IS ''");
        $this->addSql("COMMENT ON COLUMN section_planifiee.created_at IS ''");
        $this->addSql('ALTER TABLE evaluation_risque ALTER est_reevalue DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // Seuls les renommages d'index sont réversibles ; commentaires DC2Type et DEFAULT ne sont pas restaurés.
        $this->addSql('ALTER INDEX UNIQ_D19FA606C6E55B5 RENAME TO entreprise_nom_key');
        $this->addSql('ALTER INDEX IDX_BA6050FB33A6AE10 RENAME TO idx_cdp_pv_permit');
        $this->addSql('ALTER INDEX IDX_BA6050FB2E54F2F1 RENAME TO idx_cdp_pv_decide_par');
        $this->addSql('ALTER INDEX UNIQ_1A4E50C533A6AE10 RENAME TO uniq_cloture_permit_pt');
        $this->addSql('ALTER INDEX IDX_1A4E50C5B1498889 RENAME TO idx_cloture_permit_by');
        $this->addSql('ALTER INDEX UNIQ_89AD58E333A6AE10 RENAME TO uniq_pv_reception_pt');
        $this->addSql('ALTER INDEX IDX_89AD58E39DEC084D RENAME TO idx_pv_reception_by');
        $this->addSql('ALTER INDEX UNIQ_A605A71D6C6E55B5 RENAME TO uniq_categorie_risque_nom');
        $this->addSql('ALTER INDEX IDX_694309E453E00B04 RENAME TO idx_site_import_kmz');
    }
}
