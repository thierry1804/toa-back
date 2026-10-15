<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Resynchronise le nom de l'index sur permit_travail.permis_origine_id avec la convention
 * Doctrine (Version20261015180000 l'avait nommé manuellement, sans suivre le hash généré).
 */
final class Version20261016090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renomme idx_permit_travail_origine vers le nom généré par Doctrine';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER INDEX idx_permit_travail_origine RENAME TO "IDX_7A679E57A947A0B8"');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER INDEX "IDX_7A679E57A947A0B8" RENAME TO idx_permit_travail_origine');
    }
}
