<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260727110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed menu item + accesses for /hse/dashboard (Dashboard KPIs HSE)';
    }

    public function up(Schema $schema): void
    {
        // Insert menu item if absent
        $this->addSql(<<<'SQL'
            INSERT INTO "menu" (name, icon, route, position, is_active, parent_id)
            SELECT 'Dashboard KPIs HSE', 'BarChart2', '/hse/dashboard', 99, true, NULL
            WHERE NOT EXISTS (
                SELECT 1 FROM "menu" WHERE route = '/hse/dashboard'
            )
        SQL);

        // Insert menu_access rows for HSE/ADMIN/SUPER_ADMIN
        $this->addSql(<<<'SQL'
            INSERT INTO "menu_access" (menu_id, role_name, can_view, can_create, can_edit, can_delete)
            SELECT m.id, r.role_name, true, false, false, false
            FROM "menu" m
            CROSS JOIN (
                VALUES ('ROLE_HSE'), ('ROLE_ADMIN'), ('ROLE_SUPER_ADMIN')
            ) AS r(role_name)
            WHERE m.route = '/hse/dashboard'
              AND NOT EXISTS (
                  SELECT 1 FROM "menu_access" ma
                  WHERE ma.menu_id = m.id AND ma.role_name = r.role_name
              )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DELETE FROM "menu_access"
            WHERE menu_id = (SELECT id FROM "menu" WHERE route = '/hse/dashboard')
        SQL);

        $this->addSql(<<<'SQL'
            DELETE FROM "menu" WHERE route = '/hse/dashboard'
        SQL);
    }
}
