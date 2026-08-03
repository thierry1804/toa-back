<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260803100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop project_description from activity_planning; add section_planifiee and tache_planifiee tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_planning DROP COLUMN project_description');

        $this->addSql(<<<'SQL'
            CREATE TABLE section_planifiee (
                id          UUID                        NOT NULL,
                planning_id INT                         NOT NULL,
                libelle     VARCHAR(150)                NOT NULL,
                ordre       INT                         NOT NULL,
                created_at  TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql("COMMENT ON COLUMN section_planifiee.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN section_planifiee.created_at IS '(DC2Type:datetime_immutable)'");

        $this->addSql('CREATE INDEX idx_section_planning_ordre ON section_planifiee (planning_id, ordre)');

        $this->addSql(<<<'SQL'
            ALTER TABLE section_planifiee
                ADD CONSTRAINT fk_section_planning
                FOREIGN KEY (planning_id) REFERENCES activity_planning (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE tache_planifiee (
                id         UUID         NOT NULL,
                section_id UUID         NOT NULL,
                ordre      INT          NOT NULL,
                tache      VARCHAR(255) NOT NULL,
                materiel   VARCHAR(255) DEFAULT NULL,
                qui        VARCHAR(150) DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql("COMMENT ON COLUMN tache_planifiee.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN tache_planifiee.section_id IS '(DC2Type:uuid)'");

        $this->addSql('CREATE INDEX idx_tache_section_ordre ON tache_planifiee (section_id, ordre)');

        $this->addSql(<<<'SQL'
            ALTER TABLE tache_planifiee
                ADD CONSTRAINT fk_tache_section
                FOREIGN KEY (section_id) REFERENCES section_planifiee (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tache_planifiee DROP CONSTRAINT fk_tache_section');
        $this->addSql('DROP TABLE tache_planifiee');

        $this->addSql('ALTER TABLE section_planifiee DROP CONSTRAINT fk_section_planning');
        $this->addSql('DROP TABLE section_planifiee');

        $this->addSql("ALTER TABLE activity_planning ADD COLUMN project_description TEXT NOT NULL DEFAULT ''");
        $this->addSql("ALTER TABLE activity_planning ALTER COLUMN project_description DROP DEFAULT");
    }
}
