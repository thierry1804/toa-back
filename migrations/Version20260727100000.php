<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260727100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add extended KPI columns to kpi_intervention (taux_incidents, validation times, avancement_moyen)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE kpi_intervention
                ADD COLUMN IF NOT EXISTS taux_incidents             DOUBLE PRECISION NULL,
                ADD COLUMN IF NOT EXISTS temps_moyen_validation_plan DOUBLE PRECISION NULL,
                ADD COLUMN IF NOT EXISTS temps_moyen_validation_permis DOUBLE PRECISION NULL,
                ADD COLUMN IF NOT EXISTS temps_moyen_validation_pv   DOUBLE PRECISION NULL,
                ADD COLUMN IF NOT EXISTS avancement_moyen            DOUBLE PRECISION NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE kpi_intervention
                DROP COLUMN IF EXISTS taux_incidents,
                DROP COLUMN IF EXISTS temps_moyen_validation_plan,
                DROP COLUMN IF EXISTS temps_moyen_validation_permis,
                DROP COLUMN IF EXISTS temps_moyen_validation_pv,
                DROP COLUMN IF EXISTS avancement_moyen
        SQL);
    }
}
