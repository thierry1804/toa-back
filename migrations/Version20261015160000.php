<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** R-29 : questionnaire des risques saisi dans le suivi journalier. */
final class Version20261015160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'suivi_journalier : ajoute evaluation_risques (JSON)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE suivi_journalier ADD evaluation_risques JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE suivi_journalier DROP evaluation_risques');
    }
}
