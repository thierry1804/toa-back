<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260709100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create permit_travail, permit_travail_document, permit_travail_groupe tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE "permit_travail" (
            id                      UUID NOT NULL,
            reference               VARCHAR(255) NOT NULL,
            code_site               VARCHAR(100) NOT NULL,
            plan_prevention_id      UUID DEFAULT NULL,
            type                    VARCHAR(50) NOT NULL,
            processus               VARCHAR(50) NOT NULL,
            statut                  VARCHAR(50) NOT NULL DEFAULT \'BROUILLON\',
            description_travaux     TEXT DEFAULT NULL,
            date_debut_prevue       TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            date_fin_prevue         TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            engagement_accepte      BOOLEAN NOT NULL DEFAULT FALSE,
            engagement_accepte_at   TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_by_id           INT NOT NULL,
            created_at              TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('COMMENT ON COLUMN "permit_travail".id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN "permit_travail".plan_prevention_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN "permit_travail".date_debut_prevue IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN "permit_travail".date_fin_prevue IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN "permit_travail".engagement_accepte_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN "permit_travail".created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_permit_travail_reference ON "permit_travail" (reference)');
        $this->addSql('CREATE INDEX IDX_permit_travail_statut ON "permit_travail" (statut)');
        $this->addSql('CREATE INDEX IDX_permit_travail_plan ON "permit_travail" (plan_prevention_id)');
        $this->addSql('ALTER TABLE "permit_travail"
            ADD CONSTRAINT FK_permit_travail_plan_prevention
                FOREIGN KEY (plan_prevention_id)
                REFERENCES "plan_prevention" (id)
                ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "permit_travail"
            ADD CONSTRAINT FK_permit_travail_created_by
                FOREIGN KEY (created_by_id)
                REFERENCES "user" (id)
                ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE "permit_travail_document" (
            id                  UUID NOT NULL,
            permit_travail_id   UUID NOT NULL,
            type                VARCHAR(100) NOT NULL,
            file_path           TEXT NOT NULL,
            mime_type           VARCHAR(255) NOT NULL,
            uploaded_at         TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('COMMENT ON COLUMN "permit_travail_document".id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN "permit_travail_document".permit_travail_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE INDEX IDX_permit_travail_document_permit ON "permit_travail_document" (permit_travail_id)');
        $this->addSql('ALTER TABLE "permit_travail_document"
            ADD CONSTRAINT FK_permit_travail_document_permit
                FOREIGN KEY (permit_travail_id)
                REFERENCES "permit_travail" (id)
                ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE "permit_travail_groupe" (
            id                      UUID NOT NULL,
            code_site               VARCHAR(100) NOT NULL,
            plan_prevention_id      UUID DEFAULT NULL,
            permit_general_id       UUID NOT NULL,
            permit_specialise_id    UUID DEFAULT NULL,
            created_by_id           INT NOT NULL,
            created_at              TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('COMMENT ON COLUMN "permit_travail_groupe".id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN "permit_travail_groupe".plan_prevention_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN "permit_travail_groupe".permit_general_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN "permit_travail_groupe".permit_specialise_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN "permit_travail_groupe".created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_groupe_general ON "permit_travail_groupe" (permit_general_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_groupe_specialise ON "permit_travail_groupe" (permit_specialise_id)');
        $this->addSql('CREATE INDEX IDX_groupe_code_site_plan ON "permit_travail_groupe" (code_site, plan_prevention_id)');
        $this->addSql('ALTER TABLE "permit_travail_groupe"
            ADD CONSTRAINT FK_groupe_plan_prevention
                FOREIGN KEY (plan_prevention_id)
                REFERENCES "plan_prevention" (id)
                ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "permit_travail_groupe"
            ADD CONSTRAINT FK_groupe_permit_general
                FOREIGN KEY (permit_general_id)
                REFERENCES "permit_travail" (id)
                ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "permit_travail_groupe"
            ADD CONSTRAINT FK_groupe_permit_specialise
                FOREIGN KEY (permit_specialise_id)
                REFERENCES "permit_travail" (id)
                ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "permit_travail_groupe"
            ADD CONSTRAINT FK_groupe_created_by
                FOREIGN KEY (created_by_id)
                REFERENCES "user" (id)
                ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "permit_travail_groupe" DROP CONSTRAINT FK_groupe_plan_prevention');
        $this->addSql('ALTER TABLE "permit_travail_groupe" DROP CONSTRAINT FK_groupe_permit_general');
        $this->addSql('ALTER TABLE "permit_travail_groupe" DROP CONSTRAINT FK_groupe_permit_specialise');
        $this->addSql('ALTER TABLE "permit_travail_groupe" DROP CONSTRAINT FK_groupe_created_by');
        $this->addSql('DROP TABLE "permit_travail_groupe"');
        $this->addSql('ALTER TABLE "permit_travail_document" DROP CONSTRAINT FK_permit_travail_document_permit');
        $this->addSql('DROP TABLE "permit_travail_document"');
        $this->addSql('ALTER TABLE "permit_travail" DROP CONSTRAINT FK_permit_travail_plan_prevention');
        $this->addSql('ALTER TABLE "permit_travail" DROP CONSTRAINT FK_permit_travail_created_by');
        $this->addSql('DROP TABLE "permit_travail"');
    }
}
