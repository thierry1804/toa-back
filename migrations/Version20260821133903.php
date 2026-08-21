<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260821133903 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE risque_prevention_categorie_risque (
              risque_prevention_id UUID NOT NULL,
              categorie_risque_id UUID NOT NULL,
              PRIMARY KEY (
                risque_prevention_id, categorie_risque_id
              )
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_D10CAAD3CF701BC2 ON risque_prevention_categorie_risque (risque_prevention_id)');
        $this->addSql('CREATE INDEX IDX_D10CAAD3E3932B14 ON risque_prevention_categorie_risque (categorie_risque_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              risque_prevention_categorie_risque
            ADD
              CONSTRAINT FK_D10CAAD3CF701BC2 FOREIGN KEY (risque_prevention_id) REFERENCES "risque_prevention" (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              risque_prevention_categorie_risque
            ADD
              CONSTRAINT FK_D10CAAD3E3932B14 FOREIGN KEY (categorie_risque_id) REFERENCES "categorie_risque" (id) ON DELETE CASCADE
        SQL);
        $this->addSql('ALTER TABLE categorie_risque ADD parent_id UUID DEFAULT NULL');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              categorie_risque
            ADD
              CONSTRAINT FK_A605A71D727ACA70 FOREIGN KEY (parent_id) REFERENCES "categorie_risque" (id) ON DELETE
            SET
              NULL NOT DEFERRABLE
        SQL);
        $this->addSql('CREATE INDEX IDX_A605A71D727ACA70 ON categorie_risque (parent_id)');
        $this->addSql('ALTER TABLE plan_prevention ADD process VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE risque_prevention_categorie_risque DROP CONSTRAINT FK_D10CAAD3CF701BC2');
        $this->addSql('ALTER TABLE risque_prevention_categorie_risque DROP CONSTRAINT FK_D10CAAD3E3932B14');
        $this->addSql('DROP TABLE risque_prevention_categorie_risque');
        $this->addSql('ALTER TABLE "categorie_risque" DROP CONSTRAINT FK_A605A71D727ACA70');
        $this->addSql('DROP INDEX IDX_A605A71D727ACA70');
        $this->addSql('ALTER TABLE "categorie_risque" DROP parent_id');
        $this->addSql('ALTER TABLE "plan_prevention" DROP process');
    }
}
