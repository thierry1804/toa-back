<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260714100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add installations and equipements JSON columns to plan_prevention';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "plan_prevention" ADD installations JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE "plan_prevention" ADD equipements JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "plan_prevention" DROP COLUMN installations');
        $this->addSql('ALTER TABLE "plan_prevention" DROP COLUMN equipements');
    }
}
