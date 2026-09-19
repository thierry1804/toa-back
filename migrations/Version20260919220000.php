<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * plan_prevention.vehicules (Version20260805*) n'est plus mappé ni utilisé par le code
 * et est NULL sur toutes les lignes : on retire la colonne orpheline.
 */
final class Version20260919220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supprime la colonne orpheline plan_prevention.vehicules';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plan_prevention DROP COLUMN IF EXISTS vehicules');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plan_prevention ADD COLUMN IF NOT EXISTS vehicules JSON DEFAULT NULL');
    }
}
