<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260707100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Google Maps fields to site_prevention (adresse, altitude, description, couleur_marqueur, ordre_affichage)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "site_prevention" ADD COLUMN adresse VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "site_prevention" ADD COLUMN altitude DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE "site_prevention" ADD COLUMN description TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE "site_prevention" ADD COLUMN couleur_marqueur VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE "site_prevention" ADD COLUMN ordre_affichage INT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "site_prevention" DROP COLUMN adresse');
        $this->addSql('ALTER TABLE "site_prevention" DROP COLUMN altitude');
        $this->addSql('ALTER TABLE "site_prevention" DROP COLUMN description');
        $this->addSql('ALTER TABLE "site_prevention" DROP COLUMN couleur_marqueur');
        $this->addSql('ALTER TABLE "site_prevention" DROP COLUMN ordre_affichage');
    }
}
