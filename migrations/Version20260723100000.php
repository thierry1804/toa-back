<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260723100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clôture Permis de Travail: tables cloture_permit et pv_reception_pdf';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE "cloture_permit" (
            id UUID NOT NULL,
            permit_travail_id UUID NOT NULL,
            cloture_by_id INT DEFAULT NULL,
            type_cloture VARCHAR(20) NOT NULL,
            date_cloture_effective TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            commentaire TEXT DEFAULT NULL,
            accord_client BOOLEAN NOT NULL,
            accord_client_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CLOTURE_PERMIT_PT ON "cloture_permit" (permit_travail_id)');
        $this->addSql('CREATE INDEX IDX_CLOTURE_PERMIT_BY ON "cloture_permit" (cloture_by_id)');
        $this->addSql('ALTER TABLE "cloture_permit" ADD CONSTRAINT FK_CLOTURE_PERMIT_PT FOREIGN KEY (permit_travail_id) REFERENCES "permit_travail" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "cloture_permit" ADD CONSTRAINT FK_CLOTURE_PERMIT_BY FOREIGN KEY (cloture_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE "pv_reception_pdf" (
            id UUID NOT NULL,
            permit_travail_id UUID NOT NULL,
            genere_par_id INT NOT NULL,
            file_path TEXT DEFAULT NULL,
            genere_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            taille_fichier INT DEFAULT NULL,
            job_id VARCHAR(36) NOT NULL,
            statut VARCHAR(20) NOT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PV_RECEPTION_PT ON "pv_reception_pdf" (permit_travail_id)');
        $this->addSql('CREATE INDEX IDX_PV_RECEPTION_BY ON "pv_reception_pdf" (genere_par_id)');
        $this->addSql('ALTER TABLE "pv_reception_pdf" ADD CONSTRAINT FK_PV_RECEPTION_PT FOREIGN KEY (permit_travail_id) REFERENCES "permit_travail" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "pv_reception_pdf" ADD CONSTRAINT FK_PV_RECEPTION_BY FOREIGN KEY (genere_par_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "cloture_permit" DROP CONSTRAINT FK_CLOTURE_PERMIT_PT');
        $this->addSql('ALTER TABLE "cloture_permit" DROP CONSTRAINT FK_CLOTURE_PERMIT_BY');
        $this->addSql('DROP TABLE "cloture_permit"');

        $this->addSql('ALTER TABLE "pv_reception_pdf" DROP CONSTRAINT FK_PV_RECEPTION_PT');
        $this->addSql('ALTER TABLE "pv_reception_pdf" DROP CONSTRAINT FK_PV_RECEPTION_BY');
        $this->addSql('DROP TABLE "pv_reception_pdf"');
    }
}
