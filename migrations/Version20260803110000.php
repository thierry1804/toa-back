<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260803110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop site_number and region columns from activity_planning';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_planning DROP COLUMN site_number');
        $this->addSql('ALTER TABLE activity_planning DROP COLUMN region');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE activity_planning ADD COLUMN site_number VARCHAR(255) NOT NULL DEFAULT ''");
        $this->addSql("ALTER TABLE activity_planning ADD COLUMN region VARCHAR(255) NOT NULL DEFAULT ''");
        $this->addSql('ALTER TABLE activity_planning ALTER COLUMN site_number DROP DEFAULT');
        $this->addSql('ALTER TABLE activity_planning ALTER COLUMN region DROP DEFAULT');
    }
}
