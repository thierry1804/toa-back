<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260713130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create decision_hse_permit_travail table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE "decision_hse_permit_travail" (
                id UUID NOT NULL,
                permit_travail_id UUID NOT NULL,
                decide_par_id INT NOT NULL,
                decision VARCHAR(10) NOT NULL,
                commentaire TEXT DEFAULT NULL,
                signature_electronique VARCHAR(64) NOT NULL,
                decided_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql('CREATE INDEX IDX_DHE_PT_PERMIT ON "decision_hse_permit_travail" (permit_travail_id)');
        $this->addSql('CREATE INDEX IDX_DHE_PT_DECIDE_PAR ON "decision_hse_permit_travail" (decide_par_id)');

        $this->addSql('ALTER TABLE "decision_hse_permit_travail" ADD CONSTRAINT FK_DHE_PT_PERMIT FOREIGN KEY (permit_travail_id) REFERENCES "permit_travail" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE "decision_hse_permit_travail" ADD CONSTRAINT FK_DHE_PT_DECIDE_PAR FOREIGN KEY (decide_par_id) REFERENCES "user" (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "decision_hse_permit_travail" DROP CONSTRAINT FK_DHE_PT_PERMIT');
        $this->addSql('ALTER TABLE "decision_hse_permit_travail" DROP CONSTRAINT FK_DHE_PT_DECIDE_PAR');
        $this->addSql('DROP TABLE "decision_hse_permit_travail"');
    }
}
