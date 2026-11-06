<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Déplace la liste des installations et matériels utilisés du plan de
 * prévention vers le permis de travail Général (Nouveau site) : la colonne
 * existante du plan reste en place (lecture historique), ce nouveau champ
 * reçoit les saisies futures côté permis.
 */
final class Version20261105150726 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute permit_travail.installations (Permis Général, Nouveau site)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE permit_travail ADD installations JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE permit_travail DROP installations');
    }
}
