<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260716000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Resync action_key/role_action identity sequences; seed missing permit_travail action keys and role actions';
    }

    public function up(Schema $schema): void
    {
        // 1. Resync sequences (SERIAL→IDENTITY left them at 1 while rows already exist)
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

        // 2. Seed missing permit_travail action keys (suivi/logs already seeded in Version20260714000000)
        $this->addSql(<<<'SQL'
            INSERT INTO "action_key" (key, label, module, ownership_field) VALUES
                ('permit_travail.view',        'Consulter un permis de travail',                        'permit_travail', 'created_by'),
                ('permit_travail.create',      'Créer un permis de travail',                            'permit_travail', NULL),
                ('permit_travail.edit',        'Modifier un permis de travail (statut BROUILLON)',       'permit_travail', 'created_by'),
                ('permit_travail.submit',      'Soumettre un permis de travail',                        'permit_travail', 'created_by'),
                ('permit_travail.valider_hse', 'Valider un permis de travail (HSE)',                    'permit_travail', NULL),
                ('permit_travail.refuser_hse', 'Refuser un permis de travail (HSE)',                    'permit_travail', NULL),
                ('permit_travail.generate_pdf','Générer le PDF officiel d''un permis de travail validé','permit_travail', NULL),
                ('permit_travail.resoumettre', 'Resoumettre un permis de travail refusé',               'permit_travail', 'created_by')
            ON CONFLICT (key) DO NOTHING
        SQL);

        // 3. Seed role_actions for the missing keys
        $this->addSql(<<<'SQL'
            INSERT INTO "role_action" (role_name, action_key, bypass_ownership) VALUES
                -- permit_travail.view
                ('ROLE_SUPER_ADMIN',  'permit_travail.view',         true),
                ('ROLE_HSE',          'permit_travail.view',         true),
                ('ROLE_CHEF_PROJET',  'permit_travail.view',         true),
                ('ROLE_PRESTATAIRE',  'permit_travail.view',         false),
                -- permit_travail.create
                ('ROLE_SUPER_ADMIN',  'permit_travail.create',       true),
                ('ROLE_HSE',          'permit_travail.create',       true),
                ('ROLE_PRESTATAIRE',  'permit_travail.create',       true),
                -- permit_travail.edit
                ('ROLE_SUPER_ADMIN',  'permit_travail.edit',         true),
                ('ROLE_HSE',          'permit_travail.edit',         true),
                ('ROLE_PRESTATAIRE',  'permit_travail.edit',         false),
                -- permit_travail.submit
                ('ROLE_SUPER_ADMIN',  'permit_travail.submit',       true),
                ('ROLE_HSE',          'permit_travail.submit',       true),
                ('ROLE_PRESTATAIRE',  'permit_travail.submit',       false),
                -- permit_travail.valider_hse
                ('ROLE_SUPER_ADMIN',  'permit_travail.valider_hse',  true),
                ('ROLE_ADMIN',        'permit_travail.valider_hse',  true),
                ('ROLE_HSE',          'permit_travail.valider_hse',  true),
                -- permit_travail.refuser_hse
                ('ROLE_SUPER_ADMIN',  'permit_travail.refuser_hse',  true),
                ('ROLE_ADMIN',        'permit_travail.refuser_hse',  true),
                ('ROLE_HSE',          'permit_travail.refuser_hse',  true),
                -- permit_travail.generate_pdf
                ('ROLE_SUPER_ADMIN',  'permit_travail.generate_pdf', true),
                ('ROLE_ADMIN',        'permit_travail.generate_pdf', true),
                ('ROLE_HSE',          'permit_travail.generate_pdf', true),
                -- permit_travail.resoumettre
                ('ROLE_SUPER_ADMIN',  'permit_travail.resoumettre',  true),
                ('ROLE_PRESTATAIRE',  'permit_travail.resoumettre',  false)
            ON CONFLICT (role_name, action_key) DO NOTHING
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DELETE FROM "role_action" WHERE action_key IN (
                'permit_travail.view', 'permit_travail.create', 'permit_travail.edit',
                'permit_travail.submit', 'permit_travail.valider_hse', 'permit_travail.refuser_hse',
                'permit_travail.generate_pdf', 'permit_travail.resoumettre'
            )
        SQL);
        $this->addSql(<<<'SQL'
            DELETE FROM "action_key" WHERE key IN (
                'permit_travail.view', 'permit_travail.create', 'permit_travail.edit',
                'permit_travail.submit', 'permit_travail.valider_hse', 'permit_travail.refuser_hse',
                'permit_travail.generate_pdf', 'permit_travail.resoumettre'
            )
        SQL);
    }
}
