<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260720100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add fokontany, commune, district columns to site_prevention';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_prevention ADD fokontany VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE site_prevention ADD commune VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE site_prevention ADD district VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_prevention DROP COLUMN fokontany');
        $this->addSql('ALTER TABLE site_prevention DROP COLUMN commune');
        $this->addSql('ALTER TABLE site_prevention DROP COLUMN district');
    }
}
