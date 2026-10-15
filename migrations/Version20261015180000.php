<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** R-24 : un renouvellement de permis est un nouveau permis lié à l'origine (suffixe R1, R2…). */
final class Version20261015180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'permit_travail : permis_origine_id et numero_renouvellement';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE permit_travail ADD permis_origine_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE permit_travail ADD numero_renouvellement INT DEFAULT NULL');
        $this->addSql('ALTER TABLE permit_travail ADD CONSTRAINT FK_PERMIT_TRAVAIL_ORIGINE FOREIGN KEY (permis_origine_id) REFERENCES permit_travail (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_PERMIT_TRAVAIL_ORIGINE ON permit_travail (permis_origine_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE permit_travail DROP CONSTRAINT FK_PERMIT_TRAVAIL_ORIGINE');
        $this->addSql('DROP INDEX IDX_PERMIT_TRAVAIL_ORIGINE');
        $this->addSql('ALTER TABLE permit_travail DROP permis_origine_id');
        $this->addSql('ALTER TABLE permit_travail DROP numero_renouvellement');
    }
}
