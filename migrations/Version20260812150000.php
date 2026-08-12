<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Baseline no-op migration: restores a registered migration so
 * `doctrine:migrations:migrate` can resolve the "latest" alias after
 * the migrations directory was emptied. Schema is already up to date.
 */
final class Version20260812150000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
    }

    public function down(Schema $schema): void
    {
    }
}
