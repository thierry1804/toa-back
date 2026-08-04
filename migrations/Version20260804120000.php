<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260804120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add type_intervention and sites columns to activity_planning';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_planning ADD type_intervention VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE activity_planning ADD sites JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_planning DROP COLUMN sites');
        $this->addSql('ALTER TABLE activity_planning DROP COLUMN type_intervention');
    }
}
