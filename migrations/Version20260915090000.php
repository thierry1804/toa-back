<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Retours client du 08/09/2026 : sites APN/API, phases et modes opératoires portés
 * par le plan de prévention, horodatages de soumission/validation, photos (prise de
 * vue, non applicable, consultation), entreprise interne (TOA).
 */
final class Version20260915090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Retours 08/09/2026 : APN/API, phases/modes opératoires du plan, soumis_at/validated_at, captured_at/non_applicable/consulted_at, entreprise.interne';
    }

    public function up(Schema $schema): void
    {
        // R-27 — sites en aire protégée
        $this->addSql('ALTER TABLE "site" ADD apn BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql('ALTER TABLE "site" ADD api BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql('ALTER TABLE "site_prevention" ADD apn BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql('ALTER TABLE "site_prevention" ADD api BOOLEAN DEFAULT FALSE NOT NULL');

        // R-10 — catégories de risque APN / API
        $this->addSql('ALTER TABLE "categorie_risque" ADD type_site VARCHAR(10) DEFAULT NULL');

        // R-19 — horodatages
        $this->addSql('ALTER TABLE "plan_prevention" ADD soumis_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "plan_prevention" ADD validated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "permit_travail" ADD validated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql(<<<'SQL'
            UPDATE plan_prevention pp
            SET validated_at = (
                SELECT MAX(d.decided_at) FROM decision_hse_plan_prevention d
                WHERE d.plan_prevention_id = pp.id AND d.decision = 'VALIDE'
            )
            WHERE pp.statut = 'VALIDE_HSE'
            SQL);
        $this->addSql(<<<'SQL'
            UPDATE permit_travail pt
            SET validated_at = (
                SELECT MAX(d.decided_at) FROM decision_hse_permit_travail d
                WHERE d.permit_travail_id = pt.id AND d.decision = 'VALIDE'
            )
            WHERE pt.statut IN ('VALIDE_HSE', 'EN_COURS', 'CLOTURE', 'PV_VALIDE', 'PV_REFUSE')
            SQL);

        // R-15/16/17/18 — photos : date de prise de vue, non applicable
        $this->addSql('ALTER TABLE "document_prevention" ADD captured_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "document_prevention" ADD non_applicable BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql('ALTER TABLE "permit_travail_document" ADD captured_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "permit_travail_document" ADD non_applicable BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql('ALTER TABLE "suivi_journalier_document" ADD captured_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('UPDATE "document_prevention" SET captured_at = uploaded_at');
        $this->addSql('UPDATE "permit_travail_document" SET captured_at = uploaded_at');
        $this->addSql('UPDATE "suivi_journalier_document" SET captured_at = uploaded_at');

        // R-11 — consultation des pièces
        $this->addSql('ALTER TABLE "document_prevention" ADD consulted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "document_prevention" ADD consulted_by_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_E8E94DB36616B1CC ON "document_prevention" (consulted_by_id)');
        $this->addSql('ALTER TABLE "document_prevention" ADD CONSTRAINT FK_E8E94DB36616B1CC FOREIGN KEY (consulted_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');

        // R-13 — équipe HSE interne (TOA)
        $this->addSql('ALTER TABLE "entreprise" ADD interne BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql("UPDATE \"entreprise\" SET interne = TRUE WHERE LOWER(TRIM(nom)) LIKE 'toa%'");

        // R-04 / R-08 — phases et modes opératoires portés par le plan de prévention
        $this->addSql('CREATE TABLE "phase_plan_prevention" (id UUID NOT NULL, plan_prevention_id UUID NOT NULL, libelle VARCHAR(150) NOT NULL, ordre INT NOT NULL, source_section_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_76FA016714F656BD ON "phase_plan_prevention" (plan_prevention_id)');
        $this->addSql('CREATE INDEX idx_phase_pp_plan_ordre ON "phase_plan_prevention" (plan_prevention_id, ordre)');
        $this->addSql('ALTER TABLE "phase_plan_prevention" ADD CONSTRAINT FK_76FA016714F656BD FOREIGN KEY (plan_prevention_id) REFERENCES "plan_prevention" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE TABLE "mode_operatoire_plan_prevention" (id UUID NOT NULL, phase_id UUID NOT NULL, ordre INT NOT NULL, tache VARCHAR(255) NOT NULL, materiel VARCHAR(255) DEFAULT NULL, qui VARCHAR(150) DEFAULT NULL, source_tache_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_29D0556F99091188 ON "mode_operatoire_plan_prevention" (phase_id)');
        $this->addSql('CREATE INDEX idx_mode_pp_phase_ordre ON "mode_operatoire_plan_prevention" (phase_id, ordre)');
        $this->addSql('ALTER TABLE "mode_operatoire_plan_prevention" ADD CONSTRAINT FK_29D0556F99091188 FOREIGN KEY (phase_id) REFERENCES "phase_plan_prevention" (id) ON DELETE CASCADE NOT DEFERRABLE');

        // Reprise des sections/tâches de la planification déjà lues par les plans existants.
        $this->addSql(<<<'SQL'
            INSERT INTO phase_plan_prevention (id, plan_prevention_id, libelle, ordre, source_section_id)
            SELECT gen_random_uuid(), pp.id, s.libelle, s.ordre, s.id
            FROM plan_prevention pp
            JOIN section_planifiee s ON s.planning_id = pp.planification_id
            WHERE pp.planification_id IS NOT NULL
            SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO mode_operatoire_plan_prevention (id, phase_id, ordre, tache, materiel, qui, source_tache_id)
            SELECT gen_random_uuid(), ph.id, t.ordre, t.tache, t.materiel, t.qui, t.id
            FROM phase_plan_prevention ph
            JOIN tache_planifiee t ON t.section_id = ph.source_section_id
            SQL);
        $this->addSql(<<<'SQL'
            UPDATE risque_prevention r
            SET tache_planifiee_id = m.id::text
            FROM mode_operatoire_plan_prevention m
            JOIN phase_plan_prevention ph ON ph.id = m.phase_id
            WHERE ph.plan_prevention_id = r.plan_prevention_id
              AND m.source_tache_id::text = r.tache_planifiee_id
            SQL);
        $this->addSql('ALTER TABLE "phase_plan_prevention" DROP source_section_id');
        $this->addSql('ALTER TABLE "mode_operatoire_plan_prevention" DROP source_tache_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "mode_operatoire_plan_prevention" DROP CONSTRAINT FK_29D0556F99091188');
        $this->addSql('DROP TABLE "mode_operatoire_plan_prevention"');
        $this->addSql('ALTER TABLE "phase_plan_prevention" DROP CONSTRAINT FK_76FA016714F656BD');
        $this->addSql('DROP TABLE "phase_plan_prevention"');

        $this->addSql('ALTER TABLE "entreprise" DROP interne');

        $this->addSql('ALTER TABLE "document_prevention" DROP CONSTRAINT FK_E8E94DB36616B1CC');
        $this->addSql('DROP INDEX IDX_E8E94DB36616B1CC');
        $this->addSql('ALTER TABLE "document_prevention" DROP consulted_by_id');
        $this->addSql('ALTER TABLE "document_prevention" DROP consulted_at');

        $this->addSql('ALTER TABLE "suivi_journalier_document" DROP captured_at');
        $this->addSql('ALTER TABLE "permit_travail_document" DROP non_applicable');
        $this->addSql('ALTER TABLE "permit_travail_document" DROP captured_at');
        $this->addSql('ALTER TABLE "document_prevention" DROP non_applicable');
        $this->addSql('ALTER TABLE "document_prevention" DROP captured_at');

        $this->addSql('ALTER TABLE "permit_travail" DROP validated_at');
        $this->addSql('ALTER TABLE "plan_prevention" DROP validated_at');
        $this->addSql('ALTER TABLE "plan_prevention" DROP soumis_at');

        $this->addSql('ALTER TABLE "categorie_risque" DROP type_site');

        $this->addSql('ALTER TABLE "site_prevention" DROP api');
        $this->addSql('ALTER TABLE "site_prevention" DROP apn');
        $this->addSql('ALTER TABLE "site" DROP api');
        $this->addSql('ALTER TABLE "site" DROP apn');
    }
}
