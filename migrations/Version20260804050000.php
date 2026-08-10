<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260804050000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create referentiel site table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE IF NOT EXISTS site (
            id              UUID                        NOT NULL,
            code_site       VARCHAR(100)                NOT NULL,
            nom_site        VARCHAR(255)                NOT NULL,
            latitude        DOUBLE PRECISION            NOT NULL DEFAULT 0,
            longitude       DOUBLE PRECISION            NOT NULL DEFAULT 0,
            altitude        DOUBLE PRECISION            DEFAULT NULL,
            description     TEXT                        DEFAULT NULL,
            couleur_marqueur VARCHAR(20)                DEFAULT NULL,
            region          VARCHAR(255)                DEFAULT NULL,
            commune         VARCHAR(255)                DEFAULT NULL,
            district        VARCHAR(255)                DEFAULT NULL,
            fokontany       VARCHAR(255)                DEFAULT NULL,
            situation       VARCHAR(255)                DEFAULT NULL,
            source_kmz      BOOLEAN                     NOT NULL DEFAULT FALSE,
            created_at      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        )");
        $this->addSql("COMMENT ON COLUMN site.id IS '(DC2Type:uuid)'");
        $this->addSql("CREATE UNIQUE INDEX IF NOT EXISTS uniq_site_code ON site (code_site)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS site');
    }
}
