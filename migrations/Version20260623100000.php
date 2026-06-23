<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create plan_prevention, risque_prevention, document_prevention, site_prevention tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE "plan_prevention" (id UUID NOT NULL, reference VARCHAR(255) NOT NULL, code_site VARCHAR(100) NOT NULL, localite VARCHAR(255) NOT NULL, activite_planifiee TEXT NOT NULL, date_debut TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, date_fin TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, statut VARCHAR(50) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2447DCF3AEA34913 ON "plan_prevention" (reference)');
        $this->addSql('CREATE INDEX IDX_2447DCF3B03A8386 ON "plan_prevention" (created_by_id)');
        $this->addSql('ALTER TABLE "plan_prevention" ADD CONSTRAINT FK_2447DCF3B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE "risque_prevention" (id UUID NOT NULL, description TEXT NOT NULL, gravite INT NOT NULL, probabilite INT NOT NULL, niveau_risque INT NOT NULL, mesures_preventives TEXT NOT NULL, plan_prevention_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_ABD0BD4814F656BD ON "risque_prevention" (plan_prevention_id)');
        $this->addSql('ALTER TABLE "risque_prevention" ADD CONSTRAINT FK_ABD0BD4814F656BD FOREIGN KEY (plan_prevention_id) REFERENCES "plan_prevention" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE "document_prevention" (id UUID NOT NULL, type VARCHAR(50) NOT NULL, file_path TEXT NOT NULL, mime_type VARCHAR(255) NOT NULL, uploaded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, plan_prevention_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_E8E94DB314F656BD ON "document_prevention" (plan_prevention_id)');
        $this->addSql('ALTER TABLE "document_prevention" ADD CONSTRAINT FK_E8E94DB314F656BD FOREIGN KEY (plan_prevention_id) REFERENCES "plan_prevention" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE "site_prevention" (id UUID NOT NULL, nom VARCHAR(255) NOT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, source_kmz BOOLEAN NOT NULL, plan_prevention_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_6923692014F656BD ON "site_prevention" (plan_prevention_id)');
        $this->addSql('ALTER TABLE "site_prevention" ADD CONSTRAINT FK_6923692014F656BD FOREIGN KEY (plan_prevention_id) REFERENCES "plan_prevention" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "risque_prevention" DROP CONSTRAINT FK_ABD0BD4814F656BD');
        $this->addSql('ALTER TABLE "document_prevention" DROP CONSTRAINT FK_E8E94DB314F656BD');
        $this->addSql('ALTER TABLE "site_prevention" DROP CONSTRAINT FK_6923692014F656BD');
        $this->addSql('ALTER TABLE "plan_prevention" DROP CONSTRAINT FK_2447DCF3B03A8386');
        $this->addSql('DROP TABLE "site_prevention"');
        $this->addSql('DROP TABLE "document_prevention"');
        $this->addSql('DROP TABLE "risque_prevention"');
        $this->addSql('DROP TABLE "plan_prevention"');
    }
}
