<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** R-30 : processus pris en charge par un chef de projet TOA. */
final class Version20261015170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'user : ajoute processus (JSON)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD processus JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP processus');
    }
}
