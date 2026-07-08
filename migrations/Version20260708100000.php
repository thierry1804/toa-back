<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260708100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create role table with system roles seeded';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE "role" (
            id         SERIAL NOT NULL,
            name       VARCHAR(100) NOT NULL,
            label      VARCHAR(150) NOT NULL,
            description VARCHAR(500) DEFAULT NULL,
            is_system  BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_role_name ON "role" (name)');
        $this->addSql('COMMENT ON COLUMN "role".created_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql("INSERT INTO \"role\" (name, label, is_system, created_at) VALUES
            ('ROLE_SUPER_ADMIN',   'Super Administrateur', TRUE,  NOW()),
            ('ROLE_ADMIN',         'Administrateur',        TRUE,  NOW()),
            ('ROLE_DIRECTION',     'Direction',              TRUE,  NOW()),
            ('ROLE_CHEF_PROJET',   'Chef de Projet',         TRUE,  NOW()),
            ('ROLE_HSE',           'HSE',                   TRUE,  NOW()),
            ('ROLE_COLLABORATEUR', 'Collaborateur',          TRUE,  NOW()),
            ('ROLE_PRESTATAIRE',   'Prestataire',            TRUE,  NOW())
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE "role"');
    }
}
