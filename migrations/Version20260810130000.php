<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260810130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add numeroRegistreCommerce, siegeSocial, qualiteRepresentant to entreprise table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entreprise ADD COLUMN IF NOT EXISTS numero_registre_commerce VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE entreprise ADD COLUMN IF NOT EXISTS siege_social VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE entreprise ADD COLUMN IF NOT EXISTS qualite_representant VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entreprise DROP COLUMN IF EXISTS numero_registre_commerce');
        $this->addSql('ALTER TABLE entreprise DROP COLUMN IF EXISTS siege_social');
        $this->addSql('ALTER TABLE entreprise DROP COLUMN IF EXISTS qualite_representant');
    }
}
