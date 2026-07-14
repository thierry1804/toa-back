<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260713160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create version_permit_travail table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE "version_permit_travail" (
                id                UUID         NOT NULL,
                permit_travail_id UUID         NOT NULL,
                created_by_id     INT          NOT NULL,
                numero_version    INT          NOT NULL,
                snapshot_data     JSON         NOT NULL,
                motif_resoumission TEXT        DEFAULT NULL,
                created_at        TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);

        $this->addSql('CREATE INDEX idx_vpt_permit ON "version_permit_travail" (permit_travail_id)');
        $this->addSql('CREATE INDEX idx_vpt_created_by ON "version_permit_travail" (created_by_id)');

        $this->addSql(<<<'SQL'
            ALTER TABLE "version_permit_travail"
                ADD CONSTRAINT FK_VPT_PERMIT FOREIGN KEY (permit_travail_id)
                    REFERENCES "permit_travail" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE "version_permit_travail"
                ADD CONSTRAINT FK_VPT_CREATED_BY FOREIGN KEY (created_by_id)
                    REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql('COMMENT ON COLUMN "version_permit_travail".id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN "version_permit_travail".permit_travail_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN "version_permit_travail".created_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "version_permit_travail" DROP CONSTRAINT FK_VPT_PERMIT');
        $this->addSql('ALTER TABLE "version_permit_travail" DROP CONSTRAINT FK_VPT_CREATED_BY');
        $this->addSql('DROP TABLE "version_permit_travail"');
    }
}
