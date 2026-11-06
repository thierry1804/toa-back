<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Consultation HSE des documents d\'un permis de travail avant validation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "permit_travail_document" ADD consulted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "permit_travail_document" ADD consulted_by_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_PERMIT_DOC_CONSULTED_BY ON "permit_travail_document" (consulted_by_id)');
        $this->addSql('ALTER TABLE "permit_travail_document" ADD CONSTRAINT FK_PERMIT_DOC_CONSULTED_BY FOREIGN KEY (consulted_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "permit_travail_document" DROP CONSTRAINT FK_PERMIT_DOC_CONSULTED_BY');
        $this->addSql('DROP INDEX IDX_PERMIT_DOC_CONSULTED_BY');
        $this->addSql('ALTER TABLE "permit_travail_document" DROP consulted_by_id');
        $this->addSql('ALTER TABLE "permit_travail_document" DROP consulted_at');
    }
}
