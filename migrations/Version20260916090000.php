<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * R-24 : l'indicateur « temps moyen de validation du PV » n'est plus calculé ni exposé.
 */
final class Version20260916090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'R-24 : suppression de kpi_intervention.temps_moyen_validation_pv';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE kpi_intervention DROP COLUMN IF EXISTS temps_moyen_validation_pv');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE kpi_intervention ADD COLUMN IF NOT EXISTS temps_moyen_validation_pv DOUBLE PRECISION DEFAULT NULL');
    }
}
