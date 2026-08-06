<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260806100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create installation_equipement table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE installation_equipement (
            id UUID NOT NULL,
            nom VARCHAR(255) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('COMMENT ON COLUMN installation_equipement.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN installation_equipement.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN installation_equipement.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN installation_equipement.deleted_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE UNIQUE INDEX uniq_installation_equipement_nom ON installation_equipement (nom) WHERE deleted_at IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE installation_equipement');
    }
}
