<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260716400000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Week fix: permit_travail_log table, plan_prevention columns, user columns (all idempotent)';
    }

    public function up(Schema $schema): void
    {
        // ── user columns ──────────────────────────────────────────────────────────
        $this->addSql('ALTER TABLE "user" ADD COLUMN IF NOT EXISTS numero_registre_commerce VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD COLUMN IF NOT EXISTS siege_social             VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD COLUMN IF NOT EXISTS qualite_representant     VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD COLUMN IF NOT EXISTS signature_path           VARCHAR(255) DEFAULT NULL');

        // ── plan_prevention columns ───────────────────────────────────────────────
        $this->addSql('ALTER TABLE "plan_prevention" ADD COLUMN IF NOT EXISTS installations JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE "plan_prevention" ADD COLUMN IF NOT EXISTS equipements   JSON DEFAULT NULL');

        // ── permit_travail_log table ──────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS "permit_travail_log" (
                id                UUID         NOT NULL,
                permit_travail_id UUID         NOT NULL,
                declenche_par_id  INT          DEFAULT NULL,
                action            VARCHAR(50)  NOT NULL,
                code_site         VARCHAR(100) NOT NULL,
                type_permis       VARCHAR(50)  NOT NULL,
                metadata          JSON         DEFAULT NULL,
                created_at        TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_pt_log_permit_date ON "permit_travail_log" (permit_travail_id, created_at)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_pt_log_site_type   ON "permit_travail_log" (code_site, type_permis)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_pt_log_action      ON "permit_travail_log" (action, created_at)');

        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_pt_log_permit') THEN
                    ALTER TABLE "permit_travail_log"
                        ADD CONSTRAINT FK_PT_LOG_PERMIT FOREIGN KEY (permit_travail_id)
                            REFERENCES "permit_travail" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
                END IF;
                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_pt_log_user') THEN
                    ALTER TABLE "permit_travail_log"
                        ADD CONSTRAINT FK_PT_LOG_USER FOREIGN KEY (declenche_par_id)
                            REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE;
                END IF;
            END $$
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS "permit_travail_log"');
        $this->addSql('ALTER TABLE "plan_prevention" DROP COLUMN IF EXISTS installations');
        $this->addSql('ALTER TABLE "plan_prevention" DROP COLUMN IF EXISTS equipements');
        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS signature_path');
        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS qualite_representant');
        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS siege_social');
        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS numero_registre_commerce');
    }
}
