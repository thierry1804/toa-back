<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260810120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Entreprise entity; link users and activity_planning; add menu entry for Gestion des entreprises';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS entreprise (
                id UUID NOT NULL,
                nom VARCHAR(255) NOT NULL,
                siret VARCHAR(50) DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY(id),
                UNIQUE(nom)
            )
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE "user"
                ADD COLUMN IF NOT EXISTS entreprise_id UUID DEFAULT NULL
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE "user"
                DROP CONSTRAINT IF EXISTS fk_user_entreprise
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE "user"
                ADD CONSTRAINT fk_user_entreprise
                FOREIGN KEY (entreprise_id) REFERENCES entreprise(id) ON DELETE SET NULL
                NOT VALID
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE activity_planning
                ADD COLUMN IF NOT EXISTS entreprise_id UUID DEFAULT NULL
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE activity_planning
                DROP CONSTRAINT IF EXISTS fk_activity_planning_entreprise
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE activity_planning
                ADD CONSTRAINT fk_activity_planning_entreprise
                FOREIGN KEY (entreprise_id) REFERENCES entreprise(id) ON DELETE SET NULL
                NOT VALID
        SQL);

        // Add menu entry for Gestion des entreprises under Référentiel
        $this->addSql(<<<'SQL'
            INSERT INTO menu (name, route, icon, parent_id, position, is_active)
            SELECT 'Gestion des entreprises', '/referentiel/entreprises', 'building', m.id, 99, true
            FROM menu m WHERE m.name = 'Référentiel'
            AND NOT EXISTS (
                SELECT 1 FROM menu WHERE name = 'Gestion des entreprises'
            )
        SQL);

        // Grant access to ROLE_ADMIN and ROLE_SUPER_ADMIN
        $this->addSql(<<<'SQL'
            INSERT INTO menu_access (menu_id, role_name, can_view, can_create, can_edit, can_delete)
            SELECT m.id, r.role_name, true, true, true, true
            FROM menu m
            CROSS JOIN (VALUES ('ROLE_ADMIN'), ('ROLE_SUPER_ADMIN')) AS r(role_name)
            WHERE m.name = 'Gestion des entreprises'
            AND NOT EXISTS (
                SELECT 1 FROM menu_access ma
                WHERE ma.menu_id = m.id AND ma.role_name = r.role_name
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM menu_access WHERE menu_id IN (SELECT id FROM menu WHERE name = \'Gestion des entreprises\')');
        $this->addSql('DELETE FROM menu WHERE name = \'Gestion des entreprises\'');
        $this->addSql('ALTER TABLE activity_planning DROP CONSTRAINT IF EXISTS fk_activity_planning_entreprise');
        $this->addSql('ALTER TABLE activity_planning DROP COLUMN IF EXISTS entreprise_id');
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT IF EXISTS fk_user_entreprise');
        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS entreprise_id');
        $this->addSql('DROP TABLE IF EXISTS entreprise');
    }
}
