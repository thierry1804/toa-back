<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260706124052 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ActivityPlanning: make theoretical_start_date nullable, add actual_start_date and actual_end_date';
    }

    public function up(Schema $schema): void
    {
        // Make theoretical_start_date optional (no longer required on creation)
        $this->addSql('ALTER TABLE activity_planning ALTER COLUMN theoretical_start_date DROP NOT NULL');

        // Auto-set by workflow transitions, never manually writable via API
        $this->addSql('ALTER TABLE activity_planning ADD COLUMN actual_start_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE activity_planning ADD COLUMN actual_end_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_planning DROP COLUMN actual_end_date');
        $this->addSql('ALTER TABLE activity_planning DROP COLUMN actual_start_date');
        // Restore NOT NULL: backfill any null rows before re-applying constraint
        $this->addSql('UPDATE activity_planning SET theoretical_start_date = expected_start_date WHERE theoretical_start_date IS NULL');
        $this->addSql('ALTER TABLE activity_planning ALTER COLUMN theoretical_start_date SET NOT NULL');
    }
}
