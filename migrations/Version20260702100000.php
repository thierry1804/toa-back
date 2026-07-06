<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260702100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create decision_hse_plan_prevention table (HSE validation/refusal of plans)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE "decision_hse_plan_prevention" (id UUID NOT NULL, plan_prevention_id UUID NOT NULL, decide_par_id INT NOT NULL, decision VARCHAR(10) NOT NULL, commentaire TEXT DEFAULT NULL, signature_electronique VARCHAR(64) NOT NULL, decided_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_decision_hse_plan_id ON "decision_hse_plan_prevention" (plan_prevention_id)');
        $this->addSql('CREATE INDEX IDX_decision_hse_decide_par_id ON "decision_hse_plan_prevention" (decide_par_id)');
        $this->addSql('ALTER TABLE "decision_hse_plan_prevention" ADD CONSTRAINT FK_decision_hse_plan_id FOREIGN KEY (plan_prevention_id) REFERENCES "plan_prevention" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "decision_hse_plan_prevention" ADD CONSTRAINT FK_decision_hse_decide_par_id FOREIGN KEY (decide_par_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "decision_hse_plan_prevention" DROP CONSTRAINT FK_decision_hse_plan_id');
        $this->addSql('ALTER TABLE "decision_hse_plan_prevention" DROP CONSTRAINT FK_decision_hse_decide_par_id');
        $this->addSql('DROP TABLE "decision_hse_plan_prevention"');
    }
}
