<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260706100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed activity_planning with Gantt test data (all statuses)';
    }

    public function up(Schema $schema): void
    {
        $rows = [
            // --- PLANIFIE (futures) ---
            [
                'process'              => 'Maintenance groupe électrogène',
                'provider'             => 'TechServ Madagascar',
                'provider_email'       => 'contact@techserv.mg',
                'project_description'  => 'Révision complète du groupe électrogène principal et remplacement des filtres.',
                'site_code'            => 'TNR-001',
                'site_number'          => '10001',
                'site_name'            => 'Antananarivo Centre',
                'region'               => 'Analamanga',
                'theoretical_start'    => '2026-07-10 08:00:00',
                'expected_start'       => '2026-07-12 08:00:00',
                'expected_end'         => '2026-07-14 17:00:00',
                'status'               => 'planifie',
                'permit_reference'     => 'PERM-2026-001',
                'permit_validated'     => false,
            ],
            [
                'process'              => 'Installation antenne 4G',
                'provider'             => 'Nextel Solutions',
                'provider_email'       => 'ops@nextel.mg',
                'project_description'  => 'Déploiement et configuration d\'une nouvelle antenne 4G LTE.',
                'site_code'            => 'MJN-005',
                'site_number'          => '10005',
                'site_name'            => 'Mahajanga Nord',
                'region'               => 'Boeny',
                'theoretical_start'    => '2026-07-15 07:00:00',
                'expected_start'       => '2026-07-16 07:00:00',
                'expected_end'         => '2026-07-20 17:00:00',
                'status'               => 'planifie',
                'permit_reference'     => null,
                'permit_validated'     => false,
            ],
            [
                'process'              => 'Remplacement câblage réseau',
                'provider'             => 'FiberLink SARL',
                'provider_email'       => null,
                'project_description'  => 'Remplacement intégral du câblage réseau cuivre par fibre optique.',
                'site_code'            => 'TLR-012',
                'site_number'          => '10012',
                'site_name'            => 'Toliara Sud',
                'region'               => 'Atsimo-Andrefana',
                'theoretical_start'    => '2026-07-22 08:00:00',
                'expected_start'       => '2026-07-22 08:00:00',
                'expected_end'         => '2026-07-25 17:00:00',
                'status'               => 'planifie',
                'permit_reference'     => 'PERM-2026-007',
                'permit_validated'     => true,
            ],
            // --- EN COURS (en ce moment) ---
            [
                'process'              => 'Audit sécurité infrastructure',
                'provider'             => 'SecureAudit Pro',
                'provider_email'       => 'audit@secureadit.mg',
                'project_description'  => 'Audit complet de l\'infrastructure réseau et des équipements de sécurité physique.',
                'site_code'            => 'TNR-003',
                'site_number'          => '10003',
                'site_name'            => 'Antananarivo Est',
                'region'               => 'Analamanga',
                'theoretical_start'    => '2026-07-01 08:00:00',
                'expected_start'       => '2026-07-02 08:00:00',
                'expected_end'         => '2026-07-11 17:00:00',
                'status'               => 'en_cours',
                'permit_reference'     => 'PERM-2026-003',
                'permit_validated'     => true,
            ],
            [
                'process'              => 'Déploiement onduleurs',
                'provider'             => 'PowerTech MG',
                'provider_email'       => 'support@powertech.mg',
                'project_description'  => 'Installation de 4 onduleurs industriels dans la salle serveur.',
                'site_code'            => 'FNR-008',
                'site_number'          => '10008',
                'site_name'            => 'Fianarantsoa Centre',
                'region'               => 'Haute Matsiatra',
                'theoretical_start'    => '2026-06-30 07:00:00',
                'expected_start'       => '2026-07-03 07:00:00',
                'expected_end'         => '2026-07-09 17:00:00',
                'status'               => 'en_cours',
                'permit_reference'     => null,
                'permit_validated'     => false,
            ],
            // --- STAND_BY (suspendu) ---
            [
                'process'              => 'Travaux génie civil pylône',
                'provider'             => 'BTP Mada Construction',
                'provider_email'       => 'chantier@btpmada.mg',
                'project_description'  => 'Fondations et montage d\'un nouveau pylône télécom de 40m. Suspendu en attente de permis de construire.',
                'site_code'            => 'TSV-002',
                'site_number'          => '10002',
                'site_name'            => 'Toamasina Ville',
                'region'               => 'Atsinanana',
                'theoretical_start'    => '2026-07-05 08:00:00',
                'expected_start'       => '2026-07-07 08:00:00',
                'expected_end'         => '2026-07-18 17:00:00',
                'status'               => 'stand_by',
                'permit_reference'     => 'PERM-2026-005',
                'permit_validated'     => false,
            ],
            [
                'process'              => 'Mise à niveau climatisation',
                'provider'             => 'ClimaPro Services',
                'provider_email'       => 'contact@climapro.mg',
                'project_description'  => 'Remplacement des unités de climatisation de précision en salle technique. En attente de livraison des équipements.',
                'site_code'            => 'DGO-014',
                'site_number'          => '10014',
                'site_name'            => 'Diego Suarez',
                'region'               => 'Diana',
                'theoretical_start'    => '2026-07-08 08:00:00',
                'expected_start'       => '2026-07-10 08:00:00',
                'expected_end'         => '2026-07-16 17:00:00',
                'status'               => 'stand_by',
                'permit_reference'     => null,
                'permit_validated'     => false,
            ],
            // --- VALIDE (terminé et validé) ---
            [
                'process'              => 'Remplacement batterie backup',
                'provider'             => 'EnergiePlus SARL',
                'provider_email'       => 'contact@energieplus.mg',
                'project_description'  => 'Remplacement des batteries de secours du système UPS. Intervention réalisée et validée.',
                'site_code'            => 'TNR-007',
                'site_number'          => '10007',
                'site_name'            => 'Antananarivo Ouest',
                'region'               => 'Analamanga',
                'theoretical_start'    => '2026-06-15 08:00:00',
                'expected_start'       => '2026-06-16 08:00:00',
                'expected_end'         => '2026-06-18 17:00:00',
                'status'               => 'valide',
                'permit_reference'     => 'PERM-2026-002',
                'permit_validated'     => true,
            ],
            [
                'process'              => 'Configuration VLAN infrastructure',
                'provider'             => 'NetConfig Experts',
                'provider_email'       => null,
                'project_description'  => 'Segmentation réseau par VLAN pour isolation des équipements critiques.',
                'site_code'            => 'MJN-003',
                'site_number'          => '10003',
                'site_name'            => 'Mahajanga Port',
                'region'               => 'Boeny',
                'theoretical_start'    => '2026-06-20 07:00:00',
                'expected_start'       => '2026-06-22 07:00:00',
                'expected_end'         => '2026-06-25 17:00:00',
                'status'               => 'valide',
                'permit_reference'     => null,
                'permit_validated'     => false,
            ],
            // --- BROUILLON ---
            [
                'process'              => 'Étude faisabilité extension réseau',
                'provider'             => 'ConsultTech Mada',
                'provider_email'       => 'etudes@consulttech.mg',
                'project_description'  => 'Étude préliminaire pour l\'extension du réseau fibre vers les zones rurales. En cours de validation interne.',
                'site_code'            => 'AMB-021',
                'site_number'          => '10021',
                'site_name'            => 'Ambositra',
                'region'               => 'Amoron\'i Mania',
                'theoretical_start'    => '2026-08-01 08:00:00',
                'expected_start'       => '2026-08-04 08:00:00',
                'expected_end'         => '2026-08-15 17:00:00',
                'status'               => 'brouillon',
                'permit_reference'     => null,
                'permit_validated'     => false,
            ],
            // --- ANNULE ---
            [
                'process'              => 'Déploiement caméras surveillance',
                'provider'             => 'SecuriCam Pro',
                'provider_email'       => 'info@securicam.mg',
                'project_description'  => 'Installation de 12 caméras IP pour surveillance périmétrique. Annulé suite à changement de périmètre.',
                'site_code'            => 'TSV-009',
                'site_number'          => '10009',
                'site_name'            => 'Toamasina Banlieue',
                'region'               => 'Atsinanana',
                'theoretical_start'    => '2026-06-10 08:00:00',
                'expected_start'       => '2026-06-12 08:00:00',
                'expected_end'         => '2026-06-20 17:00:00',
                'status'               => 'annule',
                'permit_reference'     => 'PERM-2026-004',
                'permit_validated'     => false,
            ],
            // --- Extra PLANIFIE (août, pour étaler le Gantt) ---
            [
                'process'              => 'Mise à jour firmware équipements',
                'provider'             => 'TechServ Madagascar',
                'provider_email'       => 'contact@techserv.mg',
                'project_description'  => 'Mise à jour du firmware de l\'ensemble des équipements réseau actifs (switches, routeurs).',
                'site_code'            => 'FNR-011',
                'site_number'          => '10011',
                'site_name'            => 'Fianarantsoa Nord',
                'region'               => 'Haute Matsiatra',
                'theoretical_start'    => '2026-07-28 08:00:00',
                'expected_start'       => '2026-07-29 08:00:00',
                'expected_end'         => '2026-08-01 17:00:00',
                'status'               => 'planifie',
                'permit_reference'     => null,
                'permit_validated'     => false,
            ],
        ];

        foreach ($rows as $row) {
            $permitRef = $row['permit_reference'] !== null
                ? $this->connection->quote($row['permit_reference'])
                : 'NULL';

            $providerEmail = $row['provider_email'] !== null
                ? $this->connection->quote($row['provider_email'])
                : 'NULL';

            $permitValidated = $row['permit_validated'] ? 'TRUE' : 'FALSE';

            $this->addSql(sprintf(
                'INSERT INTO activity_planning
                    (process, provider, provider_email, project_description,
                     site_code, site_number, site_name, region,
                     theoretical_start_date, expected_start_date, expected_end_date,
                     status, permit_reference, permit_validated, created_at, updated_at)
                 VALUES (%s, %s, %s, %s, %s, %s, %s, %s,
                         \'%s\', \'%s\', \'%s\',
                         %s, %s, %s, NOW(), NULL)',
                $this->connection->quote($row['process']),
                $this->connection->quote($row['provider']),
                $providerEmail,
                $this->connection->quote($row['project_description']),
                $this->connection->quote($row['site_code']),
                $this->connection->quote($row['site_number']),
                $this->connection->quote($row['site_name']),
                $this->connection->quote($row['region']),
                $row['theoretical_start'],
                $row['expected_start'],
                $row['expected_end'],
                $this->connection->quote($row['status']),
                $permitRef,
                $permitValidated,
            ));
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM activity_planning WHERE created_at::date = CURRENT_DATE AND process IN (
            \'Maintenance groupe électrogène\',
            \'Installation antenne 4G\',
            \'Remplacement câblage réseau\',
            \'Audit sécurité infrastructure\',
            \'Déploiement onduleurs\',
            \'Travaux génie civil pylône\',
            \'Mise à niveau climatisation\',
            \'Remplacement batterie backup\',
            \'Configuration VLAN infrastructure\',
            \'Étude faisabilité extension réseau\',
            \'Déploiement caméras surveillance\',
            \'Mise à jour firmware équipements\'
        )');
    }
}
