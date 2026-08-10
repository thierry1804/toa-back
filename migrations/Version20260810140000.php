<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260810140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop siret column from entreprise table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entreprise DROP COLUMN IF EXISTS siret');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entreprise ADD COLUMN IF NOT EXISTS siret VARCHAR(50) DEFAULT NULL');
    }
}
