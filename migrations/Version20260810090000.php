<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260810090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add evaluation fields to permit_travail';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE permit_travail ADD dangers TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE permit_travail ADD evaluation_preliminaire VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE permit_travail ADD moyens_maitrise TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE permit_travail ADD evaluation_finale VARCHAR(10) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE permit_travail DROP COLUMN dangers');
        $this->addSql('ALTER TABLE permit_travail DROP COLUMN evaluation_preliminaire');
        $this->addSql('ALTER TABLE permit_travail DROP COLUMN moyens_maitrise');
        $this->addSql('ALTER TABLE permit_travail DROP COLUMN evaluation_finale');
    }
}
