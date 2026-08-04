<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260804100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create referentiel_import_kmz_site; add import_kmz_id FK on site table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE referentiel_import_kmz_site (
            id UUID NOT NULL,
            nom_fichier VARCHAR(255) NOT NULL,
            original_filename VARCHAR(255) DEFAULT NULL,
            imported_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            nombre_sites INT NOT NULL DEFAULT 0,
            PRIMARY KEY(id)
        )');

        $this->addSql('ALTER TABLE site ADD COLUMN import_kmz_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE site ADD CONSTRAINT fk_site_import_kmz
            FOREIGN KEY (import_kmz_id) REFERENCES referentiel_import_kmz_site (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX idx_site_import_kmz ON site (import_kmz_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_site_import_kmz');
        $this->addSql('ALTER TABLE site DROP CONSTRAINT IF EXISTS fk_site_import_kmz');
        $this->addSql('ALTER TABLE site DROP COLUMN IF EXISTS import_kmz_id');
        $this->addSql('DROP TABLE IF EXISTS referentiel_import_kmz_site');
    }
}
