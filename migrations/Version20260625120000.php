<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260625120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add chef_projet to plan_prevention and create examen_plan_prevention table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "plan_prevention" ADD chef_projet_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_plan_prevention_chef_projet ON "plan_prevention" (chef_projet_id)');
        $this->addSql('ALTER TABLE "plan_prevention" ADD CONSTRAINT FK_plan_prevention_chef_projet FOREIGN KEY (chef_projet_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE "examen_plan_prevention" (id UUID NOT NULL, plan_prevention_id UUID NOT NULL, examine_par_id INT NOT NULL, examine_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, commentaire TEXT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_examen_pp_plan_id ON "examen_plan_prevention" (plan_prevention_id)');
        $this->addSql('CREATE INDEX IDX_examen_pp_examine_par_id ON "examen_plan_prevention" (examine_par_id)');
        $this->addSql('ALTER TABLE "examen_plan_prevention" ADD CONSTRAINT FK_examen_pp_plan_id FOREIGN KEY (plan_prevention_id) REFERENCES "plan_prevention" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "examen_plan_prevention" ADD CONSTRAINT FK_examen_pp_examine_par_id FOREIGN KEY (examine_par_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "examen_plan_prevention" DROP CONSTRAINT FK_examen_pp_plan_id');
        $this->addSql('ALTER TABLE "examen_plan_prevention" DROP CONSTRAINT FK_examen_pp_examine_par_id');
        $this->addSql('DROP TABLE "examen_plan_prevention"');

        $this->addSql('ALTER TABLE "plan_prevention" DROP CONSTRAINT FK_plan_prevention_chef_projet');
        $this->addSql('DROP INDEX IDX_plan_prevention_chef_projet');
        $this->addSql('ALTER TABLE "plan_prevention" DROP COLUMN chef_projet_id');
    }
}
