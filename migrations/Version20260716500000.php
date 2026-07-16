<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260716500000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Data fix: correct Permis de Travail route and set Suivi Permis parent_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE \"menu\" SET route = '/permits-travail' WHERE name = 'Permis de Travail' AND route <> '/permits-travail'");
        $this->addSql("UPDATE \"menu\" SET parent_id = (SELECT id FROM \"menu\" WHERE name = 'Permis de Travail') WHERE name = 'Suivi Permis de Travail' AND parent_id IS NULL");
    }

    public function down(Schema $schema): void
    {
    }
}
