<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260723110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create decision_cdp_pv_reception table (Validation PV Fin de Travaux par Chef de Projet)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE decision_cdp_pv_reception (
                id                    UUID        NOT NULL,
                permit_travail_id     UUID        NOT NULL,
                decide_par_id         INT         NOT NULL,
                decision              VARCHAR(10) NOT NULL,
                commentaire           TEXT        DEFAULT NULL,
                signature_electronique VARCHAR(64) NOT NULL,
                decided_at            TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN decision_cdp_pv_reception.id IS '(DC2Type:uuid)'
        SQL);

        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN decision_cdp_pv_reception.permit_travail_id IS '(DC2Type:uuid)'
        SQL);

        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN decision_cdp_pv_reception.decided_at IS '(DC2Type:datetime_immutable)'
        SQL);

        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_CDP_PV_PERMIT ON decision_cdp_pv_reception (permit_travail_id)
        SQL);

        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_CDP_PV_DECIDE_PAR ON decision_cdp_pv_reception (decide_par_id)
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE decision_cdp_pv_reception
                ADD CONSTRAINT FK_CDP_PV_PERMIT
                    FOREIGN KEY (permit_travail_id)
                    REFERENCES permit_travail (id)
                    ON DELETE CASCADE
                    NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE decision_cdp_pv_reception
                ADD CONSTRAINT FK_CDP_PV_USER
                    FOREIGN KEY (decide_par_id)
                    REFERENCES "user" (id)
                    NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE decision_cdp_pv_reception DROP CONSTRAINT FK_CDP_PV_PERMIT');
        $this->addSql('ALTER TABLE decision_cdp_pv_reception DROP CONSTRAINT FK_CDP_PV_USER');
        $this->addSql('DROP TABLE decision_cdp_pv_reception');
    }
}
