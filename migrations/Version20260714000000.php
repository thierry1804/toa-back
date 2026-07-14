<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260714000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create permit_travail_log table with indexes for daily queries and site/type filtering';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE "permit_travail_log" (
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

        $this->addSql('CREATE INDEX idx_pt_log_permit_date ON "permit_travail_log" (permit_travail_id, created_at)');
        $this->addSql('CREATE INDEX idx_pt_log_site_type ON "permit_travail_log" (code_site, type_permis)');
        $this->addSql('CREATE INDEX idx_pt_log_action ON "permit_travail_log" (action, created_at)');

        $this->addSql(<<<'SQL'
            ALTER TABLE "permit_travail_log"
                ADD CONSTRAINT FK_PT_LOG_PERMIT FOREIGN KEY (permit_travail_id)
                    REFERENCES "permit_travail" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE "permit_travail_log"
                ADD CONSTRAINT FK_PT_LOG_USER FOREIGN KEY (declenche_par_id)
                    REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql('COMMENT ON COLUMN "permit_travail_log".id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN "permit_travail_log".permit_travail_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN "permit_travail_log".created_at IS \'(DC2Type:datetime_immutable)\'');

        // Seed action keys for suivi and logs
        $this->addSql(<<<'SQL'
            INSERT INTO action_key (key, label, module, ownership_field)
            VALUES
                ('permit_travail.suivi', 'Tableau de bord suivi des permis de travail', 'permit_travail', NULL),
                ('permit_travail.logs',  'Consulter les logs journaliers d''un permis de travail', 'permit_travail', NULL)
            ON CONFLICT (key) DO NOTHING
        SQL);

        // Grant ROLE_HSE, ROLE_ADMIN, ROLE_SUPER_ADMIN access to suivi and logs
        $this->addSql(<<<'SQL'
            INSERT INTO role_action (role_name, action_key, bypass_ownership)
            VALUES
                ('ROLE_SUPER_ADMIN', 'permit_travail.suivi', true),
                ('ROLE_ADMIN',       'permit_travail.suivi', true),
                ('ROLE_HSE',         'permit_travail.suivi', true),
                ('ROLE_SUPER_ADMIN', 'permit_travail.logs',  true),
                ('ROLE_ADMIN',       'permit_travail.logs',  true),
                ('ROLE_HSE',         'permit_travail.logs',  true)
            ON CONFLICT (role_name, action_key) DO NOTHING
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "permit_travail_log" DROP CONSTRAINT FK_PT_LOG_PERMIT');
        $this->addSql('ALTER TABLE "permit_travail_log" DROP CONSTRAINT FK_PT_LOG_USER');
        $this->addSql('DROP TABLE "permit_travail_log"');
        $this->addSql("DELETE FROM role_action WHERE action_key IN ('permit_travail.suivi', 'permit_travail.logs')");
        $this->addSql("DELETE FROM action_key WHERE key IN ('permit_travail.suivi', 'permit_travail.logs')");
    }
}
