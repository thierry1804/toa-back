<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sous-traitants d\'un prestataire et leur affectation aux planifications';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sous_traitant (id UUID NOT NULL, entreprise_id UUID NOT NULL, nom VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_SOUS_TRAITANT_ENTREPRISE ON sous_traitant (entreprise_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_sous_traitant_entreprise_nom ON sous_traitant (entreprise_id, nom)');
        $this->addSql('ALTER TABLE sous_traitant ADD CONSTRAINT FK_SOUS_TRAITANT_ENTREPRISE FOREIGN KEY (entreprise_id) REFERENCES entreprise (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE "user" ADD sous_traitant_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT FK_USER_SOUS_TRAITANT FOREIGN KEY (sous_traitant_id) REFERENCES sous_traitant (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_USER_SOUS_TRAITANT ON "user" (sous_traitant_id)');
        $this->addSql('CREATE TABLE activity_planning_sous_traitant (activity_planning_id INT NOT NULL, sous_traitant_id UUID NOT NULL, PRIMARY KEY(activity_planning_id, sous_traitant_id))');
        $this->addSql('CREATE INDEX IDX_AP_ST_PLANNING ON activity_planning_sous_traitant (activity_planning_id)');
        $this->addSql('CREATE INDEX IDX_AP_ST_SOUS_TRAITANT ON activity_planning_sous_traitant (sous_traitant_id)');
        $this->addSql('ALTER TABLE activity_planning_sous_traitant ADD CONSTRAINT FK_AP_ST_PLANNING FOREIGN KEY (activity_planning_id) REFERENCES activity_planning (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE activity_planning_sous_traitant ADD CONSTRAINT FK_AP_ST_SOUS_TRAITANT FOREIGN KEY (sous_traitant_id) REFERENCES sous_traitant (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_planning_sous_traitant DROP CONSTRAINT FK_AP_ST_PLANNING');
        $this->addSql('ALTER TABLE activity_planning_sous_traitant DROP CONSTRAINT FK_AP_ST_SOUS_TRAITANT');
        $this->addSql('DROP TABLE activity_planning_sous_traitant');
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT FK_USER_SOUS_TRAITANT');
        $this->addSql('DROP INDEX IDX_USER_SOUS_TRAITANT');
        $this->addSql('ALTER TABLE "user" DROP sous_traitant_id');
        $this->addSql('ALTER TABLE sous_traitant DROP CONSTRAINT FK_SOUS_TRAITANT_ENTREPRISE');
        $this->addSql('DROP TABLE sous_traitant');
    }
}
