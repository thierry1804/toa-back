<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260810110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grant ROLE_ADMIN access to Référentiel menu items (parent + 3 sub-items)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO menu_access (menu_id, role_name, can_view, can_create, can_edit, can_delete)
            SELECT m.id, 'ROLE_ADMIN', true, true, true, true
            FROM menu m
            WHERE m.name IN ('Référentiel', 'Gestion de sites', 'Gestion de risques', 'Liste des installations et équipements')
              AND NOT EXISTS (
                  SELECT 1 FROM menu_access ma
                  WHERE ma.menu_id = m.id AND ma.role_name = 'ROLE_ADMIN'
              )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DELETE FROM menu_access
            WHERE role_name = 'ROLE_ADMIN'
              AND menu_id IN (
                  SELECT id FROM menu
                  WHERE name IN ('Référentiel', 'Gestion de sites', 'Gestion de risques', 'Liste des installations et équipements')
              )
        SQL);
    }
}
