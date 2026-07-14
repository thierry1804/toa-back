<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260713150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create permit_travail_pdf table for async PDF generation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE permit_travail_pdf (
            id UUID NOT NULL,
            permit_travail_id UUID NOT NULL,
            type_permis VARCHAR(20) DEFAULT NULL,
            file_path TEXT DEFAULT NULL,
            genere_par_id INT NOT NULL,
            genere_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            taille_fichier INT DEFAULT NULL,
            job_id VARCHAR(36) NOT NULL,
            statut VARCHAR(20) NOT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql('COMMENT ON COLUMN permit_travail_pdf.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN permit_travail_pdf.permit_travail_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN permit_travail_pdf.genere_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PT_PDF_PERMIT ON permit_travail_pdf (permit_travail_id)');
        $this->addSql('CREATE INDEX IDX_PT_PDF_GENERE_PAR ON permit_travail_pdf (genere_par_id)');
        $this->addSql('ALTER TABLE permit_travail_pdf ADD CONSTRAINT FK_PT_PDF_PERMIT FOREIGN KEY (permit_travail_id) REFERENCES permit_travail (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE permit_travail_pdf ADD CONSTRAINT FK_PT_PDF_GENERE_PAR FOREIGN KEY (genere_par_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE permit_travail_pdf DROP CONSTRAINT FK_PT_PDF_PERMIT');
        $this->addSql('ALTER TABLE permit_travail_pdf DROP CONSTRAINT FK_PT_PDF_GENERE_PAR');
        $this->addSql('DROP TABLE permit_travail_pdf');
    }
}
