<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260723120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create kpi_intervention table for PV_VALIDE KPI tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE kpi_intervention (
                id                          UUID          NOT NULL,
                code_site                   VARCHAR(50)   NOT NULL,
                periode                     VARCHAR(7)    NOT NULL,
                nb_permis_clotures_valides  INT           NOT NULL DEFAULT 0,
                nb_permis_clotures_total    INT           NOT NULL DEFAULT 0,
                taux_cloture                DOUBLE PRECISION NOT NULL DEFAULT 0,
                delai_moyen_validation_cdp  DOUBLE PRECISION NOT NULL DEFAULT 0,
                updated_at                  TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN kpi_intervention.id IS '(DC2Type:uuid)'
        SQL);

        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN kpi_intervention.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);

        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_KPI_SITE_PERIODE ON kpi_intervention (code_site, periode)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE kpi_intervention');
    }
}
