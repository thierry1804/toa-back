<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * R-14/R-23/R-26 : l'agent de terrain crée le permis, le soumet et en demande
 * la clôture. Il lui manquait les droits de soumission (le bouton « Soumettre »
 * n'apparaissait pas), de resoumission et de clôture.
 */
final class Version20261015140000 extends AbstractMigration
{
    private const KEYS = ['permit_travail.submit', 'permit_travail.resoumettre', 'permit_travail.cloturer'];

    public function getDescription(): string
    {
        return 'Droits permis de travail (soumission, resoumission, clôture) pour ROLE_AGENT_TERRAIN';
    }

    public function up(Schema $schema): void
    {
        foreach (self::KEYS as $key) {
            $this->addSql(
                'INSERT INTO role_action (role_name, action_key, bypass_ownership)
                 SELECT \'ROLE_AGENT_TERRAIN\', \'' . $key . '\', false
                 WHERE NOT EXISTS (
                     SELECT 1 FROM role_action WHERE role_name = \'ROLE_AGENT_TERRAIN\' AND action_key = \'' . $key . '\'
                 )',
            );
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::KEYS as $key) {
            $this->addSql('DELETE FROM role_action WHERE role_name = \'ROLE_AGENT_TERRAIN\' AND action_key = \'' . $key . '\'');
        }
    }
}
