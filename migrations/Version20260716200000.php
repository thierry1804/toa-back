<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260716200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing user columns: numero_registre_commerce, siege_social, qualite_representant, signature_path';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD COLUMN IF NOT EXISTS numero_registre_commerce VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD COLUMN IF NOT EXISTS siege_social             VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD COLUMN IF NOT EXISTS qualite_representant     VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD COLUMN IF NOT EXISTS signature_path           VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS numero_registre_commerce');
        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS siege_social');
        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS qualite_representant');
        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS signature_path');
    }
}
