<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260702200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create version_plan_prevention table (resoumission traceability)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE "version_plan_prevention" (id UUID NOT NULL, plan_prevention_id UUID NOT NULL, created_by_id INT NOT NULL, numero_version INT NOT NULL, snapshot_data JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, motif_resoumission TEXT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_version_plan_prevention_plan_id ON "version_plan_prevention" (plan_prevention_id)');
        $this->addSql('CREATE INDEX IDX_version_plan_prevention_created_by ON "version_plan_prevention" (created_by_id)');
        $this->addSql('ALTER TABLE "version_plan_prevention" ADD CONSTRAINT FK_version_plan_prevention_plan_id FOREIGN KEY (plan_prevention_id) REFERENCES "plan_prevention" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "version_plan_prevention" ADD CONSTRAINT FK_version_plan_prevention_created_by FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "version_plan_prevention" DROP CONSTRAINT FK_version_plan_prevention_plan_id');
        $this->addSql('ALTER TABLE "version_plan_prevention" DROP CONSTRAINT FK_version_plan_prevention_created_by');
        $this->addSql('DROP TABLE "version_plan_prevention"');
    }
}
