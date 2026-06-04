<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260601104805 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE "permit" (id UUID NOT NULL, plan_prevention_id UUID DEFAULT NULL, plan_prevention_reference VARCHAR(255) DEFAULT NULL, code_site VARCHAR(100) NOT NULL, nombre_intervenants INT NOT NULL, date_debut TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, date_fin TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, status VARCHAR(50) NOT NULL, demandeur_nom VARCHAR(255) DEFAULT NULL, demandeur_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, superviseur_nom VARCHAR(255) DEFAULT NULL, superviseur_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, creer_par VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, type VARCHAR(30) NOT NULL, intitule_travaux VARCHAR(255) DEFAULT NULL, localisation VARCHAR(255) DEFAULT NULL, contractant VARCHAR(255) DEFAULT NULL, duree_max_jours INT DEFAULT NULL, travaux_risques JSON DEFAULT NULL, permis_annexes JSON DEFAULT NULL, evaluation_risques_validee BOOLEAN DEFAULT NULL, personne_competente_assignee BOOLEAN DEFAULT NULL, mesures_prevention_mises_en_place BOOLEAN DEFAULT NULL, personnel_informe BOOLEAN DEFAULT NULL, dangers_controles BOOLEAN DEFAULT NULL, type_travail JSON DEFAULT NULL, tension JSON DEFAULT NULL, type_circuit_equipement VARCHAR(255) DEFAULT NULL, description_travail TEXT DEFAULT NULL, raison_non_mise_hors_tension TEXT DEFAULT NULL, risques JSON DEFAULT NULL, materiels JSON DEFAULT NULL, mesures_prevention JSON DEFAULT NULL, secouriste_present BOOLEAN DEFAULT NULL, numeros_urgence_disponibles BOOLEAN DEFAULT NULL, engagement_demandeur BOOLEAN DEFAULT NULL, bon_consignation JSON DEFAULT NULL, prestataire VARCHAR(255) DEFAULT NULL, region VARCHAR(100) DEFAULT NULL, description_operation TEXT DEFAULT NULL, hauteur_chute VARCHAR(50) DEFAULT NULL, travail_toiture BOOLEAN DEFAULT NULL, type_pente VARCHAR(50) DEFAULT NULL, plan_sauvetage_disponible BOOLEAN DEFAULT NULL, PRIMARY KEY (id))');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE "permit"');
    }
}
