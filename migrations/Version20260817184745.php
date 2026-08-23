<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260817184745 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add take5_record and controle_journalier tables';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE "controle_journalier" (id UUID NOT NULL, date DATE NOT NULL, intervenants JSON NOT NULL, confirmation_mesures BOOLEAN NOT NULL, vitesse_vent INT DEFAULT NULL, signature_demandeur VARCHAR(150) NOT NULL, signature_intervenant VARCHAR(150) NOT NULL, signature_cloture_demandeur VARCHAR(150) DEFAULT NULL, signature_cloture_intervenant VARCHAR(150) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, intervention_id UUID NOT NULL, created_by_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_5D1E6B728EAE3863 ON "controle_journalier" (intervention_id)');
        $this->addSql('CREATE INDEX IDX_5D1E6B72B03A8386 ON "controle_journalier" (created_by_id)');
        $this->addSql('CREATE UNIQUE INDEX uq_controle_intervention_date ON "controle_journalier" (intervention_id, date)');
        $this->addSql('CREATE TABLE "take5_record" (id UUID NOT NULL, responsable_nom VARCHAR(150) NOT NULL, equipe JSON NOT NULL, localisation VARCHAR(255) NOT NULL, tache_description TEXT NOT NULL, etape1_arreter JSON NOT NULL, etape2_observer JSON NOT NULL, etape3_analyser JSON NOT NULL, etape4_controler JSON NOT NULL, etape5_proceder JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, intervention_id UUID NOT NULL, created_by_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_C076F405B03A8386 ON "take5_record" (created_by_id)');
        $this->addSql('CREATE INDEX idx_take5_intervention ON "take5_record" (intervention_id)');
        $this->addSql('ALTER TABLE "controle_journalier" ADD CONSTRAINT FK_5D1E6B728EAE3863 FOREIGN KEY (intervention_id) REFERENCES "intervention" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE "controle_journalier" ADD CONSTRAINT FK_5D1E6B72B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE "take5_record" ADD CONSTRAINT FK_C076F4058EAE3863 FOREIGN KEY (intervention_id) REFERENCES "intervention" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE "take5_record" ADD CONSTRAINT FK_C076F405B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE');
        // Note: doctrine:migrations:diff also flagged an unrelated pre-existing duplicate FK
        // constraint on site_prevention.site_id (fk_69236920f6bd1646 vs fk_site_prevention_site,
        // leftover from an earlier out-of-band schema fix) — intentionally left untouched here,
        // out of scope for this migration.
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "controle_journalier" DROP CONSTRAINT FK_5D1E6B728EAE3863');
        $this->addSql('ALTER TABLE "controle_journalier" DROP CONSTRAINT FK_5D1E6B72B03A8386');
        $this->addSql('ALTER TABLE "take5_record" DROP CONSTRAINT FK_C076F4058EAE3863');
        $this->addSql('ALTER TABLE "take5_record" DROP CONSTRAINT FK_C076F405B03A8386');
        $this->addSql('DROP TABLE "controle_journalier"');
        $this->addSql('DROP TABLE "take5_record"');
    }
}
