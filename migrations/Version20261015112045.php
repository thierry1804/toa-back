<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261015112045 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE "referentiel_import_spreadsheet_site" (id UUID NOT NULL, nom_fichier VARCHAR(255) NOT NULL, original_filename VARCHAR(255) DEFAULT NULL, imported_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, nombre_sites INT DEFAULT 0 NOT NULL, PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE site ADD source_spreadsheet BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE site ADD import_spreadsheet_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE site ADD CONSTRAINT FK_694309E41D763350 FOREIGN KEY (import_spreadsheet_id) REFERENCES "referentiel_import_spreadsheet_site" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_694309E41D763350 ON site (import_spreadsheet_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE "referentiel_import_spreadsheet_site"');
        $this->addSql('ALTER TABLE "site" DROP CONSTRAINT FK_694309E41D763350');
        $this->addSql('DROP INDEX IDX_694309E41D763350');
        $this->addSql('ALTER TABLE "site" DROP source_spreadsheet');
        $this->addSql('ALTER TABLE "site" DROP import_spreadsheet_id');
    }
}
