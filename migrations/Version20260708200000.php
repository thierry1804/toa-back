<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260708200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create action_key and role_action tables for dynamic voter permissions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE "action_key" (
            id              SERIAL NOT NULL,
            key             VARCHAR(150) NOT NULL,
            label           VARCHAR(200) NOT NULL,
            module          VARCHAR(100) NOT NULL,
            ownership_field VARCHAR(50) DEFAULT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_action_key_key ON "action_key" (key)');

        $this->addSql('CREATE TABLE "role_action" (
            id               SERIAL NOT NULL,
            role_name        VARCHAR(100) NOT NULL,
            action_key       VARCHAR(150) NOT NULL,
            bypass_ownership BOOLEAN NOT NULL DEFAULT FALSE,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_role_action ON "role_action" (role_name, action_key)');

        $this->addSql("INSERT INTO \"action_key\" (key, label, module, ownership_field) VALUES
            ('plan_prevention.examine',     'Examiner un plan de prévention',            'plan_prevention', 'chef_projet'),
            ('plan_prevention.valider_hse', 'Valider un plan de prévention (HSE)',        'plan_prevention', NULL),
            ('plan_prevention.refuser_hse', 'Refuser un plan de prévention (HSE)',        'plan_prevention', NULL),
            ('plan_prevention.submit',      'Soumettre un plan de prévention',            'plan_prevention', 'created_by'),
            ('plan_prevention.resoumettre', 'Resoumettre un plan de prévention',          'plan_prevention', 'created_by'),
            ('plan_prevention.import_kmz',  'Importer un fichier KMZ (plan prévention)', 'plan_prevention', 'chef_projet')
        ");

        $this->addSql("INSERT INTO \"role_action\" (role_name, action_key, bypass_ownership) VALUES
            ('ROLE_SUPER_ADMIN',  'plan_prevention.examine',     TRUE),
            ('ROLE_ADMIN',        'plan_prevention.examine',     TRUE),
            ('ROLE_CHEF_PROJET',  'plan_prevention.examine',     FALSE),
            ('ROLE_SUPER_ADMIN',  'plan_prevention.valider_hse', TRUE),
            ('ROLE_ADMIN',        'plan_prevention.valider_hse', TRUE),
            ('ROLE_HSE',          'plan_prevention.valider_hse', TRUE),
            ('ROLE_SUPER_ADMIN',  'plan_prevention.refuser_hse', TRUE),
            ('ROLE_ADMIN',        'plan_prevention.refuser_hse', TRUE),
            ('ROLE_HSE',          'plan_prevention.refuser_hse', TRUE),
            ('ROLE_SUPER_ADMIN',  'plan_prevention.submit',      TRUE),
            ('ROLE_PRESTATAIRE',  'plan_prevention.submit',      FALSE),
            ('ROLE_SUPER_ADMIN',  'plan_prevention.resoumettre', TRUE),
            ('ROLE_PRESTATAIRE',  'plan_prevention.resoumettre', FALSE),
            ('ROLE_SUPER_ADMIN',  'plan_prevention.import_kmz',  TRUE),
            ('ROLE_ADMIN',        'plan_prevention.import_kmz',  TRUE),
            ('ROLE_CHEF_PROJET',  'plan_prevention.import_kmz',  FALSE)
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE "role_action"');
        $this->addSql('DROP TABLE "action_key"');
    }
}
