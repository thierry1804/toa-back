<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260905202747 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'permit_travail: replace the single dangers/evaluationPreliminaire/moyensMaitrise/evaluationFinale scalars with a JSON array of danger entries (repeater support)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE permit_travail ADD dangers_new JSON DEFAULT NULL');
        $this->addSql(<<<'SQL'
            UPDATE permit_travail
            SET dangers_new = json_build_array(json_build_object(
                'dangers', dangers,
                'moyensMaitrise', moyens_maitrise,
                'evaluationPreliminaire', evaluation_preliminaire,
                'evaluationFinale', evaluation_finale
            ))
            WHERE dangers IS NOT NULL
               OR moyens_maitrise IS NOT NULL
               OR evaluation_preliminaire IS NOT NULL
               OR evaluation_finale IS NOT NULL
            SQL);
        $this->addSql('ALTER TABLE permit_travail DROP dangers');
        $this->addSql('ALTER TABLE permit_travail DROP evaluation_preliminaire');
        $this->addSql('ALTER TABLE permit_travail DROP moyens_maitrise');
        $this->addSql('ALTER TABLE permit_travail DROP evaluation_finale');
        $this->addSql('ALTER TABLE permit_travail RENAME COLUMN dangers_new TO dangers');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE permit_travail ADD evaluation_preliminaire VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE permit_travail ADD moyens_maitrise TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE permit_travail ADD evaluation_finale VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE permit_travail ADD dangers_text TEXT DEFAULT NULL');
        $this->addSql(<<<'SQL'
            UPDATE permit_travail
            SET dangers_text = dangers->0->>'dangers',
                moyens_maitrise = dangers->0->>'moyensMaitrise',
                evaluation_preliminaire = dangers->0->>'evaluationPreliminaire',
                evaluation_finale = dangers->0->>'evaluationFinale'
            WHERE json_array_length(dangers) > 0
            SQL);
        $this->addSql('ALTER TABLE permit_travail DROP dangers');
        $this->addSql('ALTER TABLE permit_travail RENAME COLUMN dangers_text TO dangers');
    }
}
