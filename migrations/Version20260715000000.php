<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260715000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add numeroRegistreCommerce and siegeSocial to user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD numero_registre_commerce VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD siege_social VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP COLUMN numero_registre_commerce');
        $this->addSql('ALTER TABLE "user" DROP COLUMN siege_social');
    }
}
