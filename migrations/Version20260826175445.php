<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260826175445 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add notification_expiration_envoyee_at to permit_travail';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE permit_travail ADD notification_expiration_envoyee_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "permit_travail" DROP notification_expiration_envoyee_at');
    }
}
