<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260721100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create intervention and evaluation_risque tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE intervention (
                id UUID NOT NULL,
                permit_travail_id UUID NOT NULL,
                statut VARCHAR(50) NOT NULL,
                created_by_id INT NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);

        $this->addSql('COMMENT ON COLUMN intervention.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN intervention.permit_travail_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN intervention.created_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('CREATE UNIQUE INDEX uq_intervention_permit ON intervention (permit_travail_id)');
        $this->addSql('CREATE INDEX idx_intervention_created_by ON intervention (created_by_id)');

        $this->addSql(<<<'SQL'
            ALTER TABLE intervention
                ADD CONSTRAINT fk_intervention_permit_travail
                FOREIGN KEY (permit_travail_id) REFERENCES permit_travail (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE intervention
                ADD CONSTRAINT fk_intervention_created_by
                FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE evaluation_risque (
                id UUID NOT NULL,
                intervention_id UUID NOT NULL,
                risque_source_id UUID DEFAULT NULL,
                description TEXT NOT NULL,
                gravite INT NOT NULL,
                probabilite INT NOT NULL,
                niveau_risque INT NOT NULL,
                mesures_confirmees TEXT DEFAULT NULL,
                est_reevalue BOOLEAN NOT NULL DEFAULT FALSE,
                created_by_id INT NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);

        $this->addSql('COMMENT ON COLUMN evaluation_risque.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN evaluation_risque.intervention_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN evaluation_risque.risque_source_id IS \'(DC2Type:uuid)\'');

        $this->addSql('CREATE INDEX idx_eval_risque_reevalue ON evaluation_risque (intervention_id, est_reevalue)');
        $this->addSql('CREATE INDEX idx_eval_risque_created_by ON evaluation_risque (created_by_id)');

        $this->addSql(<<<'SQL'
            ALTER TABLE evaluation_risque
                ADD CONSTRAINT fk_eval_risque_intervention
                FOREIGN KEY (intervention_id) REFERENCES intervention (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE evaluation_risque
                ADD CONSTRAINT fk_eval_risque_source
                FOREIGN KEY (risque_source_id) REFERENCES risque_prevention (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE evaluation_risque
                ADD CONSTRAINT fk_eval_risque_created_by
                FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO action_key (key, label, module, ownership_field)
            VALUES
                ('intervention.create',             'Créer une intervention',                        'intervention', NULL),
                ('intervention.view',               'Consulter une intervention',                     'intervention', 'created_by'),
                ('intervention.edit',               'Modifier une évaluation de risque',             'intervention', 'created_by'),
                ('intervention.valider_evaluation', 'Valider l''évaluation des risques',             'intervention', 'created_by')
            ON CONFLICT (key) DO NOTHING
        SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO role_action (role_name, action_key, bypass_ownership)
            VALUES
                ('ROLE_SUPER_ADMIN',  'intervention.create',             true),
                ('ROLE_PRESTATAIRE',  'intervention.create',             false),
                ('ROLE_SUPER_ADMIN',  'intervention.view',               true),
                ('ROLE_PRESTATAIRE',  'intervention.view',               false),
                ('ROLE_SUPER_ADMIN',  'intervention.edit',               true),
                ('ROLE_PRESTATAIRE',  'intervention.edit',               false),
                ('ROLE_SUPER_ADMIN',  'intervention.valider_evaluation', true),
                ('ROLE_PRESTATAIRE',  'intervention.valider_evaluation', false)
            ON CONFLICT (role_name, action_key) DO NOTHING
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM role_action WHERE action_key LIKE \'intervention.%\'');
        $this->addSql('DELETE FROM action_key WHERE key LIKE \'intervention.%\'');
        $this->addSql('ALTER TABLE evaluation_risque DROP CONSTRAINT fk_eval_risque_intervention');
        $this->addSql('ALTER TABLE evaluation_risque DROP CONSTRAINT fk_eval_risque_source');
        $this->addSql('ALTER TABLE evaluation_risque DROP CONSTRAINT fk_eval_risque_created_by');
        $this->addSql('ALTER TABLE intervention DROP CONSTRAINT fk_intervention_permit_travail');
        $this->addSql('ALTER TABLE intervention DROP CONSTRAINT fk_intervention_created_by');
        $this->addSql('DROP TABLE evaluation_risque');
        $this->addSql('DROP TABLE intervention');
    }
}
