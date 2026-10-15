<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** R-01 : le rôle « Chef de projet Prestataire » doit exister dans la table des rôles (attribution depuis l'administration). */
final class Version20261016100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rôle système ROLE_CHEF_PROJET_PRESTATAIRE';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'INSERT INTO "role" (name, label, is_system, created_at)
             SELECT \'ROLE_CHEF_PROJET_PRESTATAIRE\', \'Chef de projet Prestataire\', true, NOW()
             WHERE NOT EXISTS (SELECT 1 FROM "role" WHERE name = \'ROLE_CHEF_PROJET_PRESTATAIRE\')',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM "role" WHERE name = \'ROLE_CHEF_PROJET_PRESTATAIRE\' AND is_system = true');
    }
}
