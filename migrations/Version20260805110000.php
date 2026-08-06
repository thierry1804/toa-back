<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260805110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tache_planifiee_id + risque_residuel to risque_prevention; add planification_id to plan_prevention';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE risque_prevention ADD tache_planifiee_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE risque_prevention ADD risque_residuel INT DEFAULT NULL');
        $this->addSql('ALTER TABLE plan_prevention ADD planification_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE risque_prevention DROP COLUMN tache_planifiee_id');
        $this->addSql('ALTER TABLE risque_prevention DROP COLUMN risque_residuel');
        $this->addSql('ALTER TABLE plan_prevention DROP COLUMN planification_id');
    }
}
