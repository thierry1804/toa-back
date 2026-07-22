<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260721135125 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER INDEX idx_ap_created_by RENAME TO IDX_74930FF8B03A8386');
        $this->addSql('ALTER INDEX idx_dhe_pt_permit RENAME TO IDX_3B4D3A3B33A6AE10');
        $this->addSql('ALTER INDEX idx_dhe_pt_decide_par RENAME TO IDX_3B4D3A3B2E54F2F1');
        $this->addSql('COMMENT ON COLUMN evaluation_risque.id IS \'\'');
        $this->addSql('COMMENT ON COLUMN evaluation_risque.intervention_id IS \'\'');
        $this->addSql('COMMENT ON COLUMN evaluation_risque.risque_source_id IS \'\'');
        $this->addSql('ALTER INDEX idx_eval_risque_created_by RENAME TO IDX_EA5C38D3B03A8386');
        $this->addSql('COMMENT ON COLUMN intervention.id IS \'\'');
        $this->addSql('COMMENT ON COLUMN intervention.permit_travail_id IS \'\'');
        $this->addSql('COMMENT ON COLUMN intervention.created_at IS \'\'');
        $this->addSql('ALTER INDEX idx_intervention_created_by RENAME TO IDX_D11814ABB03A8386');
        $this->addSql('COMMENT ON COLUMN permit_travail_log.id IS \'\'');
        $this->addSql('COMMENT ON COLUMN permit_travail_log.permit_travail_id IS \'\'');
        $this->addSql('COMMENT ON COLUMN permit_travail_log.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN permit_travail_pdf.id IS \'\'');
        $this->addSql('COMMENT ON COLUMN permit_travail_pdf.permit_travail_id IS \'\'');
        $this->addSql('COMMENT ON COLUMN permit_travail_pdf.genere_at IS \'\'');
        $this->addSql('ALTER INDEX uniq_pt_pdf_permit RENAME TO UNIQ_78A005D533A6AE10');
        $this->addSql('ALTER INDEX idx_pt_pdf_genere_par RENAME TO IDX_78A005D59DEC084D');
        $this->addSql('COMMENT ON COLUMN version_permit_travail.id IS \'\'');
        $this->addSql('COMMENT ON COLUMN version_permit_travail.permit_travail_id IS \'\'');
        $this->addSql('COMMENT ON COLUMN version_permit_travail.created_at IS \'\'');
        $this->addSql('ALTER INDEX idx_vpt_permit RENAME TO IDX_C3C1FA7733A6AE10');
        $this->addSql('ALTER INDEX idx_vpt_created_by RENAME TO IDX_C3C1FA77B03A8386');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER INDEX idx_74930ff8b03a8386 RENAME TO idx_ap_created_by');
        $this->addSql('ALTER INDEX idx_3b4d3a3b2e54f2f1 RENAME TO idx_dhe_pt_decide_par');
        $this->addSql('ALTER INDEX idx_3b4d3a3b33a6ae10 RENAME TO idx_dhe_pt_permit');
        $this->addSql('COMMENT ON COLUMN "evaluation_risque".id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN "evaluation_risque".intervention_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN "evaluation_risque".risque_source_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER INDEX idx_ea5c38d3b03a8386 RENAME TO idx_eval_risque_created_by');
        $this->addSql('COMMENT ON COLUMN "intervention".id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN "intervention".created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN "intervention".permit_travail_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER INDEX idx_d11814abb03a8386 RENAME TO idx_intervention_created_by');
        $this->addSql('COMMENT ON COLUMN permit_travail_log.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN permit_travail_log.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN permit_travail_log.permit_travail_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN "permit_travail_pdf".id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN "permit_travail_pdf".genere_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN "permit_travail_pdf".permit_travail_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER INDEX uniq_78a005d533a6ae10 RENAME TO uniq_pt_pdf_permit');
        $this->addSql('ALTER INDEX idx_78a005d59dec084d RENAME TO idx_pt_pdf_genere_par');
        $this->addSql('COMMENT ON COLUMN "version_permit_travail".id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN "version_permit_travail".created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN "version_permit_travail".permit_travail_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER INDEX idx_c3c1fa7733a6ae10 RENAME TO idx_vpt_permit');
        $this->addSql('ALTER INDEX idx_c3c1fa77b03a8386 RENAME TO idx_vpt_created_by');
    }
}
