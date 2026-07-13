<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260713140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add created_by_id to activity_planning for ownership-based access control';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_planning ADD created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE activity_planning ADD CONSTRAINT FK_AP_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_AP_CREATED_BY ON activity_planning (created_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_planning DROP CONSTRAINT FK_AP_CREATED_BY');
        $this->addSql('DROP INDEX IDX_AP_CREATED_BY');
        $this->addSql('ALTER TABLE activity_planning DROP created_by_id');
    }
}
