<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260805100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reference_activite to plan_prevention';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plan_prevention ADD reference_activite VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plan_prevention DROP COLUMN reference_activite');
    }
}
