<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260722134001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE "suivi_journalier" (id UUID NOT NULL, date DATE NOT NULL, nom_responsable VARCHAR(150) NOT NULL, realise BOOLEAN NOT NULL, motif_non_realisation TEXT DEFAULT NULL, avancement_pourcentage INT NOT NULL, commentaire TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, intervention_id UUID NOT NULL, created_by_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_956A07D98EAE3863 ON "suivi_journalier" (intervention_id)');
        $this->addSql('CREATE INDEX IDX_956A07D9B03A8386 ON "suivi_journalier" (created_by_id)');
        $this->addSql('CREATE UNIQUE INDEX uq_suivi_intervention_date ON "suivi_journalier" (intervention_id, date)');
        $this->addSql('CREATE TABLE "suivi_journalier_document" (id UUID NOT NULL, file_path TEXT NOT NULL, mime_type VARCHAR(100) NOT NULL, nom VARCHAR(255) NOT NULL, uploaded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, suivi_journalier_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_26A5A53663FD59B ON "suivi_journalier_document" (suivi_journalier_id)');
        $this->addSql('ALTER TABLE "suivi_journalier" ADD CONSTRAINT FK_956A07D98EAE3863 FOREIGN KEY (intervention_id) REFERENCES "intervention" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE "suivi_journalier" ADD CONSTRAINT FK_956A07D9B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE "suivi_journalier_document" ADD CONSTRAINT FK_26A5A53663FD59B FOREIGN KEY (suivi_journalier_id) REFERENCES "suivi_journalier" (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "suivi_journalier" DROP CONSTRAINT FK_956A07D98EAE3863');
        $this->addSql('ALTER TABLE "suivi_journalier" DROP CONSTRAINT FK_956A07D9B03A8386');
        $this->addSql('ALTER TABLE "suivi_journalier_document" DROP CONSTRAINT FK_26A5A53663FD59B');
        $this->addSql('DROP TABLE "suivi_journalier"');
        $this->addSql('DROP TABLE "suivi_journalier_document"');
    }
}
