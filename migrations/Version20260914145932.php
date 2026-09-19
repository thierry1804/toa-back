<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * R-04 : sections/modes opératoires saisis par le prestataire dans le plan de
 * prévention (remplace la saisie côté planification — SectionPlanifiee/
 * TachePlanifiee restent en base, lecture seule, pour l'historique).
 */
final class Version20260914145932 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'R-04 : ajoute section_prevention et mode_operatoire (saisie prestataire sur le plan de prévention)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE section_prevention (
              id UUID NOT NULL,
              libelle VARCHAR(150) NOT NULL,
              ordre INT NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              plan_prevention_id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_D49ADC3A14F656BD ON section_prevention (plan_prevention_id)');
        $this->addSql('CREATE INDEX idx_section_prevention_plan_ordre ON section_prevention (plan_prevention_id, ordre)');
        $this->addSql(<<<'SQL'
            ALTER TABLE section_prevention
            ADD CONSTRAINT FK_D49ADC3A14F656BD FOREIGN KEY (plan_prevention_id) REFERENCES plan_prevention (id) ON DELETE CASCADE NOT DEFERRABLE
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE mode_operatoire (
              id UUID NOT NULL,
              ordre INT NOT NULL,
              mode_operatoire VARCHAR(255) NOT NULL,
              materiel VARCHAR(255) DEFAULT NULL,
              qui VARCHAR(150) DEFAULT NULL,
              section_id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_DE8C7DB9D823E37A ON mode_operatoire (section_id)');
        $this->addSql('CREATE INDEX idx_mode_operatoire_section_ordre ON mode_operatoire (section_id, ordre)');
        $this->addSql(<<<'SQL'
            ALTER TABLE mode_operatoire
            ADD CONSTRAINT FK_DE8C7DB9D823E37A FOREIGN KEY (section_id) REFERENCES section_prevention (id) ON DELETE CASCADE NOT DEFERRABLE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mode_operatoire DROP CONSTRAINT FK_DE8C7DB9D823E37A');
        $this->addSql('ALTER TABLE section_prevention DROP CONSTRAINT FK_D49ADC3A14F656BD');
        $this->addSql('DROP TABLE mode_operatoire');
        $this->addSql('DROP TABLE section_prevention');
    }
}
