<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260708300000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create plan_prevention_pdf table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE "plan_prevention_pdf" (
            id                   UUID NOT NULL,
            plan_prevention_id   UUID NOT NULL,
            genere_par_id        INT NOT NULL,
            file_path            TEXT DEFAULT NULL,
            genere_at            TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            taille_fichier       INT DEFAULT NULL,
            job_id               VARCHAR(36) NOT NULL,
            statut               VARCHAR(20) NOT NULL DEFAULT \'EN_COURS\',
            PRIMARY KEY (id)
        )');
        $this->addSql('COMMENT ON COLUMN "plan_prevention_pdf".id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN "plan_prevention_pdf".plan_prevention_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN "plan_prevention_pdf".genere_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_pdf_plan ON "plan_prevention_pdf" (plan_prevention_id)');
        $this->addSql('CREATE INDEX IDX_pdf_job_id ON "plan_prevention_pdf" (job_id)');
        $this->addSql('ALTER TABLE "plan_prevention_pdf"
            ADD CONSTRAINT FK_pdf_plan_prevention
                FOREIGN KEY (plan_prevention_id)
                REFERENCES "plan_prevention" (id)
                ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "plan_prevention_pdf"
            ADD CONSTRAINT FK_pdf_genere_par
                FOREIGN KEY (genere_par_id)
                REFERENCES "user" (id)
                ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "plan_prevention_pdf" DROP CONSTRAINT FK_pdf_plan_prevention');
        $this->addSql('ALTER TABLE "plan_prevention_pdf" DROP CONSTRAINT FK_pdf_genere_par');
        $this->addSql('DROP TABLE "plan_prevention_pdf"');
    }
}
