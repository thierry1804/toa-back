<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260716100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Consolidated: permit_travail_log table, user columns, plan_prevention columns, sequence resync, full action_key/role_action seed';
    }

    public function up(Schema $schema): void
    {
        // ── 1. Resync identity sequences (SERIAL→IDENTITY left them at 1) ─────────
        $this->addSql(<<<'SQL'
            DO $$
            DECLARE v INT;
            BEGIN
                SELECT COALESCE(MAX(id), 0) + 1 INTO v FROM "action_key";
                EXECUTE format('ALTER TABLE action_key ALTER COLUMN id RESTART WITH %s', v);
                SELECT COALESCE(MAX(id), 0) + 1 INTO v FROM "role_action";
                EXECUTE format('ALTER TABLE role_action ALTER COLUMN id RESTART WITH %s', v);
            END $$
        SQL);

        // ── 2. permit_travail_log table ───────────────────────────────────────────
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
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'fk_pt_log_permit'
                ) THEN
                    ALTER TABLE "permit_travail_log"
                        ADD CONSTRAINT FK_PT_LOG_PERMIT FOREIGN KEY (permit_travail_id)
                            REFERENCES "permit_travail" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
                END IF;
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'fk_pt_log_user'
                ) THEN
                    ALTER TABLE "permit_travail_log"
                        ADD CONSTRAINT FK_PT_LOG_USER FOREIGN KEY (declenche_par_id)
                            REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE;
                END IF;
            END $$
        SQL);

        // ── 3. plan_prevention columns ────────────────────────────────────────────
        $this->addSql('ALTER TABLE "plan_prevention" ADD COLUMN IF NOT EXISTS installations JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE "plan_prevention" ADD COLUMN IF NOT EXISTS equipements    JSON DEFAULT NULL');

        // ── 4. user columns ───────────────────────────────────────────────────────
        $this->addSql('ALTER TABLE "user" ADD COLUMN IF NOT EXISTS numero_registre_commerce VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD COLUMN IF NOT EXISTS siege_social             VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD COLUMN IF NOT EXISTS qualite_representant     VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD COLUMN IF NOT EXISTS signature_path           VARCHAR(255) DEFAULT NULL');

        // ── 5. action_key seeds ───────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            INSERT INTO "action_key" (key, label, module, ownership_field) VALUES
                ('permit_travail.view',        'Consulter un permis de travail',                         'permit_travail', 'created_by'),
                ('permit_travail.create',      'Créer un permis de travail',                             'permit_travail', NULL),
                ('permit_travail.edit',        'Modifier un permis de travail (statut BROUILLON)',        'permit_travail', 'created_by'),
                ('permit_travail.submit',      'Soumettre un permis de travail',                         'permit_travail', 'created_by'),
                ('permit_travail.valider_hse', 'Valider un permis de travail (HSE)',                     'permit_travail', NULL),
                ('permit_travail.refuser_hse', 'Refuser un permis de travail (HSE)',                     'permit_travail', NULL),
                ('permit_travail.generate_pdf','Générer le PDF officiel d''un permis de travail validé', 'permit_travail', NULL),
                ('permit_travail.resoumettre', 'Resoumettre un permis de travail refusé',                'permit_travail', 'created_by'),
                ('permit_travail.suivi',       'Tableau de bord suivi des permis de travail',            'permit_travail', NULL),
                ('permit_travail.logs',        'Consulter les logs journaliers d''un permis de travail', 'permit_travail', NULL),
                ('user.upload_signature',      'Uploader sa signature électronique',                     'user',           'id')
            ON CONFLICT (key) DO NOTHING
        SQL);

        // ── 6. role_action seeds ──────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            INSERT INTO "role_action" (role_name, action_key, bypass_ownership) VALUES
                -- permit_travail.view
                ('ROLE_SUPER_ADMIN', 'permit_travail.view',         true),
                ('ROLE_HSE',         'permit_travail.view',         true),
                ('ROLE_CHEF_PROJET', 'permit_travail.view',         true),
                ('ROLE_PRESTATAIRE', 'permit_travail.view',         false),
                -- permit_travail.create
                ('ROLE_SUPER_ADMIN', 'permit_travail.create',       true),
                ('ROLE_HSE',         'permit_travail.create',       true),
                ('ROLE_PRESTATAIRE', 'permit_travail.create',       true),
                -- permit_travail.edit
                ('ROLE_SUPER_ADMIN', 'permit_travail.edit',         true),
                ('ROLE_HSE',         'permit_travail.edit',         true),
                ('ROLE_PRESTATAIRE', 'permit_travail.edit',         false),
                -- permit_travail.submit
                ('ROLE_SUPER_ADMIN', 'permit_travail.submit',       true),
                ('ROLE_HSE',         'permit_travail.submit',       true),
                ('ROLE_PRESTATAIRE', 'permit_travail.submit',       false),
                -- permit_travail.valider_hse
                ('ROLE_SUPER_ADMIN', 'permit_travail.valider_hse',  true),
                ('ROLE_ADMIN',       'permit_travail.valider_hse',  true),
                ('ROLE_HSE',         'permit_travail.valider_hse',  true),
                -- permit_travail.refuser_hse
                ('ROLE_SUPER_ADMIN', 'permit_travail.refuser_hse',  true),
                ('ROLE_ADMIN',       'permit_travail.refuser_hse',  true),
                ('ROLE_HSE',         'permit_travail.refuser_hse',  true),
                -- permit_travail.generate_pdf
                ('ROLE_SUPER_ADMIN', 'permit_travail.generate_pdf', true),
                ('ROLE_ADMIN',       'permit_travail.generate_pdf', true),
                ('ROLE_HSE',         'permit_travail.generate_pdf', true),
                -- permit_travail.resoumettre
                ('ROLE_SUPER_ADMIN', 'permit_travail.resoumettre',  true),
                ('ROLE_PRESTATAIRE', 'permit_travail.resoumettre',  false),
                -- permit_travail.suivi
                ('ROLE_SUPER_ADMIN', 'permit_travail.suivi',        true),
                ('ROLE_ADMIN',       'permit_travail.suivi',        true),
                ('ROLE_HSE',         'permit_travail.suivi',        true),
                -- permit_travail.logs
                ('ROLE_SUPER_ADMIN', 'permit_travail.logs',         true),
                ('ROLE_ADMIN',       'permit_travail.logs',         true),
                ('ROLE_HSE',         'permit_travail.logs',         true),
                -- user.upload_signature
                ('ROLE_PRESTATAIRE', 'user.upload_signature',       false),
                ('ROLE_ADMIN',       'user.upload_signature',       true),
                ('ROLE_SUPER_ADMIN', 'user.upload_signature',       true)
            ON CONFLICT (role_name, action_key) DO NOTHING
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DELETE FROM "role_action" WHERE action_key IN (
                'permit_travail.view', 'permit_travail.create', 'permit_travail.edit',
                'permit_travail.submit', 'permit_travail.valider_hse', 'permit_travail.refuser_hse',
                'permit_travail.generate_pdf', 'permit_travail.resoumettre',
                'permit_travail.suivi', 'permit_travail.logs', 'user.upload_signature'
            )
        SQL);
        $this->addSql(<<<'SQL'
            DELETE FROM "action_key" WHERE key IN (
                'permit_travail.view', 'permit_travail.create', 'permit_travail.edit',
                'permit_travail.submit', 'permit_travail.valider_hse', 'permit_travail.refuser_hse',
                'permit_travail.generate_pdf', 'permit_travail.resoumettre',
                'permit_travail.suivi', 'permit_travail.logs', 'user.upload_signature'
            )
        SQL);
        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS signature_path');
        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS qualite_representant');
        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS siege_social');
        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS numero_registre_commerce');
        $this->addSql('ALTER TABLE "plan_prevention" DROP COLUMN IF EXISTS equipements');
        $this->addSql('ALTER TABLE "plan_prevention" DROP COLUMN IF EXISTS installations');
        $this->addSql('DROP TABLE IF EXISTS "permit_travail_log"');
    }
}
