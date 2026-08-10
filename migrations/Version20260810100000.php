<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260810100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add type_site, zone, type_pylone, hauteur_pylone to site table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site ADD COLUMN IF NOT EXISTS type_site    VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE site ADD COLUMN IF NOT EXISTS zone         VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE site ADD COLUMN IF NOT EXISTS type_pylone  VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE site ADD COLUMN IF NOT EXISTS hauteur_pylone DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site DROP COLUMN IF EXISTS type_site');
        $this->addSql('ALTER TABLE site DROP COLUMN IF EXISTS zone');
        $this->addSql('ALTER TABLE site DROP COLUMN IF EXISTS type_pylone');
        $this->addSql('ALTER TABLE site DROP COLUMN IF EXISTS hauteur_pylone');
    }
}
