<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260706125248 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Permit: add actual_start_date and actual_end_date (auto-set on workflow transitions, never writable via API)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE permit ADD COLUMN actual_start_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE permit ADD COLUMN actual_end_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE permit DROP COLUMN actual_end_date');
        $this->addSql('ALTER TABLE permit DROP COLUMN actual_start_date');
    }
}
