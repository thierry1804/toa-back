<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260806110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add type_permis to categorie_risque';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE categorie_risque ADD type_permis VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE categorie_risque DROP COLUMN type_permis');
    }
}
