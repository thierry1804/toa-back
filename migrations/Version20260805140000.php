<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260805140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add type_intervention column to plan_prevention';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plan_prevention ADD type_intervention VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plan_prevention DROP COLUMN type_intervention');
    }
}
