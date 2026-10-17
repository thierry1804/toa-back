<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Aligne la matrice admin sur le travail terrain.
 * L'admin peut ensuite recocher ces cases : ce ne sont pas des interdictions figées.
 */
final class Version20261017120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agent de terrain : plans de prévention en consultation, suivis journaliers créables et modifiables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'UPDATE "menu_access" AS access
             SET can_create = false, can_edit = false, can_delete = false
             FROM "menu"
             WHERE access.menu_id = "menu".id
               AND "menu".route = \'/prevention\'
               AND access.role_name = \'ROLE_AGENT_TERRAIN\'',
        );

        $this->addSql(
            'INSERT INTO "menu_access" (menu_id, role_name, can_view, can_create, can_edit, can_delete)
             SELECT "menu".id, \'ROLE_AGENT_TERRAIN\', true, false, false, false
             FROM "menu"
             WHERE "menu".route = \'/prevention\'
               AND NOT EXISTS (
                   SELECT 1 FROM "menu_access" existing
                   WHERE existing.menu_id = "menu".id AND existing.role_name = \'ROLE_AGENT_TERRAIN\'
               )',
        );

        $this->addSql(
            'UPDATE "menu_access" AS access
             SET can_view = true, can_create = true, can_edit = true
             FROM "menu"
             WHERE access.menu_id = "menu".id
               AND "menu".route = \'/interventions/suivis\'
               AND access.role_name IN (\'ROLE_AGENT_TERRAIN\', \'ROLE_PRESTATAIRE\', \'ROLE_SUPER_ADMIN\')',
        );

        $this->addSql(
            'INSERT INTO "menu_access" (menu_id, role_name, can_view, can_create, can_edit, can_delete)
             SELECT "menu".id, role_name, true, true, true, false
             FROM "menu"
             CROSS JOIN (VALUES (\'ROLE_AGENT_TERRAIN\'), (\'ROLE_PRESTATAIRE\'), (\'ROLE_SUPER_ADMIN\')) AS roles(role_name)
             WHERE "menu".route = \'/interventions/suivis\'
               AND NOT EXISTS (
                   SELECT 1 FROM "menu_access" existing
                   WHERE existing.menu_id = "menu".id AND existing.role_name = roles.role_name
               )',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'UPDATE "menu_access" AS access
             SET can_create = false, can_edit = false
             FROM "menu"
             WHERE access.menu_id = "menu".id
               AND "menu".route = \'/interventions/suivis\'
               AND access.role_name IN (\'ROLE_AGENT_TERRAIN\', \'ROLE_PRESTATAIRE\', \'ROLE_SUPER_ADMIN\')',
        );
    }
}
