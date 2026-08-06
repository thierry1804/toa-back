<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260805130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create categorie_risque table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE categorie_risque (id UUID NOT NULL, nom VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CATEGORIE_RISQUE_NOM ON categorie_risque (nom)');
        $this->addSql('COMMENT ON COLUMN categorie_risque.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN categorie_risque.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN categorie_risque.updated_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE categorie_risque');
    }
}
