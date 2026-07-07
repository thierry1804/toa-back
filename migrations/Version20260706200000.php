<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260706200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed additional activity_planning rows for multi-month Gantt testing (Apr 2026 – Jan 2027)';
    }

    public function up(Schema $schema): void
    {
        $rows = [
            // ── APRIL 2026 (past, completed) ─────────────────────────────────
            [
                'process'             => 'Inspection pylône hauteur',
                'provider'            => 'AltitudeTech SARL',
                'provider_email'      => 'ops@altitudetech.mg',
                'project_description' => 'Inspection périodique et relevé d\'état du pylône de 60m.',
                'site_code'           => 'MJN-001',
                'site_number'         => '20001',
                'site_name'           => 'Mahajanga Central',
                'region'              => 'Boeny',
                'theoretical_start'   => '2026-04-02 08:00:00',
                'expected_start'      => '2026-04-03 08:00:00',
                'expected_end'        => '2026-04-05 17:00:00',
                'status'              => 'valide',
                'permit_reference'    => 'PERM-2026-010',
                'permit_validated'    => true,
            ],
            [
                'process'             => 'Remplacement groupe électrogène 50 kVA',
                'provider'            => 'PowerTech MG',
                'provider_email'      => 'support@powertech.mg',
                'project_description' => 'Dépose de l\'ancien groupe et pose d\'un nouveau groupe 50 kVA.',
                'site_code'           => 'TSV-004',
                'site_number'         => '20002',
                'site_name'           => 'Toamasina Nord',
                'region'              => 'Atsinanana',
                'theoretical_start'   => '2026-04-07 07:00:00',
                'expected_start'      => '2026-04-08 07:00:00',
                'expected_end'        => '2026-04-12 17:00:00',
                'status'              => 'valide',
                'permit_reference'    => null,
                'permit_validated'    => false,
            ],
            [
                'process'             => 'Câblage alimentation redondante',
                'provider'            => 'ElecPro Mada',
                'provider_email'      => null,
                'project_description' => 'Mise en place d\'une alimentation électrique redondante N+1.',
                'site_code'           => 'DGO-002',
                'site_number'         => '20003',
                'site_name'           => 'Diego Suarez Port',
                'region'              => 'Diana',
                'theoretical_start'   => '2026-04-14 08:00:00',
                'expected_start'      => '2026-04-15 08:00:00',
                'expected_end'        => '2026-04-25 17:00:00',
                'status'              => 'valide',
                'permit_reference'    => 'PERM-2026-011',
                'permit_validated'    => true,
            ],

            // ── MAY 2026 (past, mixed) ────────────────────────────────────────
            [
                'process'             => 'Audit conformité équipements',
                'provider'            => 'SecureAudit Pro',
                'provider_email'      => 'audit@secureaudit.mg',
                'project_description' => 'Vérification de la conformité réglementaire de l\'ensemble des équipements.',
                'site_code'           => 'FNR-003',
                'site_number'         => '20004',
                'site_name'           => 'Fianarantsoa Ouest',
                'region'              => 'Haute Matsiatra',
                'theoretical_start'   => '2026-05-05 08:00:00',
                'expected_start'      => '2026-05-06 08:00:00',
                'expected_end'        => '2026-05-09 17:00:00',
                'status'              => 'valide',
                'permit_reference'    => null,
                'permit_validated'    => false,
            ],
            [
                'process'             => 'Installation système anti-intrusion',
                'provider'            => 'SecuriCam Pro',
                'provider_email'      => 'info@securicam.mg',
                'project_description' => 'Pose de détecteurs de mouvement PIR et centrale d\'alarme.',
                'site_code'           => 'TNR-015',
                'site_number'         => '20005',
                'site_name'           => 'Antananarivo Sud',
                'region'              => 'Analamanga',
                'theoretical_start'   => '2026-05-12 08:00:00',
                'expected_start'      => '2026-05-13 08:00:00',
                'expected_end'        => '2026-05-16 17:00:00',
                'status'              => 'annule',
                'permit_reference'    => 'PERM-2026-014',
                'permit_validated'    => false,
            ],
            [
                'process'             => 'Migration équipements actifs vers NGN',
                'provider'            => 'NetConfig Experts',
                'provider_email'      => null,
                'project_description' => 'Remplacement des équipements legacy par une infrastructure Next Generation Network.',
                'site_code'           => 'AMB-005',
                'site_number'         => '20006',
                'site_name'           => 'Ambovombe',
                'region'              => 'Androy',
                'theoretical_start'   => '2026-05-20 07:00:00',
                'expected_start'      => '2026-05-22 07:00:00',
                'expected_end'        => '2026-06-04 17:00:00',
                'status'              => 'valide',
                'permit_reference'    => 'PERM-2026-015',
                'permit_validated'    => true,
            ],

            // ── AUGUST 2026 (near future) ─────────────────────────────────────
            [
                'process'             => 'Déploiement réseau LoRa IoT',
                'provider'            => 'IoTConnect SARL',
                'provider_email'      => 'deploy@iotconnect.mg',
                'project_description' => 'Installation de 8 gateways LoRaWAN pour supervision des équipements distants.',
                'site_code'           => 'TNR-020',
                'site_number'         => '20007',
                'site_name'           => 'Antananarivo Périphérie',
                'region'              => 'Analamanga',
                'theoretical_start'   => '2026-08-03 08:00:00',
                'expected_start'      => '2026-08-04 08:00:00',
                'expected_end'        => '2026-08-12 17:00:00',
                'status'              => 'planifie',
                'permit_reference'    => null,
                'permit_validated'    => false,
            ],
            [
                'process'             => 'Rénovation salle serveur',
                'provider'            => 'BTP Mada Construction',
                'provider_email'      => 'chantier@btpmada.mg',
                'project_description' => 'Travaux de rénovation complète de la salle serveur : faux plancher, refroidissement, sécurité.',
                'site_code'           => 'TSV-006',
                'site_number'         => '20008',
                'site_name'           => 'Toamasina Centre',
                'region'              => 'Atsinanana',
                'theoretical_start'   => '2026-08-10 08:00:00',
                'expected_start'      => '2026-08-11 08:00:00',
                'expected_end'        => '2026-08-28 17:00:00',
                'status'              => 'planifie',
                'permit_reference'    => 'PERM-2026-020',
                'permit_validated'    => true,
            ],
            [
                'process'             => 'Test charge réseau fibre',
                'provider'            => 'FiberLink SARL',
                'provider_email'      => null,
                'project_description' => 'Tests de montée en charge et mesures de performance sur nouvelle infrastructure fibre.',
                'site_code'           => 'MJN-010',
                'site_number'         => '20009',
                'site_name'           => 'Mahajanga Aéroport',
                'region'              => 'Boeny',
                'theoretical_start'   => '2026-08-18 08:00:00',
                'expected_start'      => '2026-08-19 08:00:00',
                'expected_end'        => '2026-08-21 17:00:00',
                'status'              => 'stand_by',
                'permit_reference'    => null,
                'permit_validated'    => false,
            ],

            // ── SEPTEMBER 2026 ────────────────────────────────────────────────
            [
                'process'             => 'Mise à niveau infrastructure SDH',
                'provider'            => 'TechServ Madagascar',
                'provider_email'      => 'contact@techserv.mg',
                'project_description' => 'Remplacement des cartes SDH obsolètes par des équipements DWDM nouvelle génération.',
                'site_code'           => 'FNR-015',
                'site_number'         => '20010',
                'site_name'           => 'Fianarantsoa Est',
                'region'              => 'Haute Matsiatra',
                'theoretical_start'   => '2026-09-01 08:00:00',
                'expected_start'      => '2026-09-02 08:00:00',
                'expected_end'        => '2026-09-15 17:00:00',
                'status'              => 'planifie',
                'permit_reference'    => 'PERM-2026-025',
                'permit_validated'    => false,
            ],
            [
                'process'             => 'Déploiement supervision SNMP',
                'provider'            => 'NetConfig Experts',
                'provider_email'      => null,
                'project_description' => 'Mise en place d\'agents SNMP v3 et intégration dans le système de supervision centralisé.',
                'site_code'           => 'TNR-025',
                'site_number'         => '20011',
                'site_name'           => 'Antananarivo Nord',
                'region'              => 'Analamanga',
                'theoretical_start'   => '2026-09-08 07:00:00',
                'expected_start'      => '2026-09-09 07:00:00',
                'expected_end'        => '2026-09-11 17:00:00',
                'status'              => 'brouillon',
                'permit_reference'    => null,
                'permit_validated'    => false,
            ],
            [
                'process'             => 'Extension capacité stockage SAN',
                'provider'            => 'StoragePro MG',
                'provider_email'      => 'contact@storagepro.mg',
                'project_description' => 'Ajout de 48 To de capacité sur le SAN existant et migration des données critiques.',
                'site_code'           => 'TSV-011',
                'site_number'         => '20012',
                'site_name'           => 'Toamasina Port',
                'region'              => 'Atsinanana',
                'theoretical_start'   => '2026-09-15 08:00:00',
                'expected_start'      => '2026-09-16 08:00:00',
                'expected_end'        => '2026-09-30 17:00:00',
                'status'              => 'planifie',
                'permit_reference'    => 'PERM-2026-028',
                'permit_validated'    => true,
            ],

            // ── OCTOBER 2026 ──────────────────────────────────────────────────
            [
                'process'             => 'Remplacement batteries UPS 200 Ah',
                'provider'            => 'EnergiePlus SARL',
                'provider_email'      => 'contact@energieplus.mg',
                'project_description' => 'Renouvellement du parc batteries des onduleurs critiques (durée vie 5 ans atteinte).',
                'site_code'           => 'DGO-008',
                'site_number'         => '20013',
                'site_name'           => 'Diego Suarez Ville',
                'region'              => 'Diana',
                'theoretical_start'   => '2026-10-05 08:00:00',
                'expected_start'      => '2026-10-06 08:00:00',
                'expected_end'        => '2026-10-09 17:00:00',
                'status'              => 'planifie',
                'permit_reference'    => null,
                'permit_validated'    => false,
            ],
            [
                'process'             => 'Audit sécurité physique périmétrique',
                'provider'            => 'SecureAudit Pro',
                'provider_email'      => 'audit@secureaudit.mg',
                'project_description' => 'Contrôle complet des accès physiques, clôtures, gardiennage et vidéosurveillance.',
                'site_code'           => 'AMB-015',
                'site_number'         => '20014',
                'site_name'           => 'Ambatondrazaka',
                'region'              => 'Alaotra-Mangoro',
                'theoretical_start'   => '2026-10-13 08:00:00',
                'expected_start'      => '2026-10-14 08:00:00',
                'expected_end'        => '2026-10-16 17:00:00',
                'status'              => 'stand_by',
                'permit_reference'    => 'PERM-2026-031',
                'permit_validated'    => false,
            ],
            [
                'process'             => 'Déploiement solution backup cloud',
                'provider'            => 'CloudSafe SARL',
                'provider_email'      => 'backup@cloudsafe.mg',
                'project_description' => 'Mise en place de la sauvegarde externalisée vers datacenter cloud privé.',
                'site_code'           => 'TNR-030',
                'site_number'         => '20015',
                'site_name'           => 'Antananarivo Datacenter',
                'region'              => 'Analamanga',
                'theoretical_start'   => '2026-10-20 08:00:00',
                'expected_start'      => '2026-10-21 08:00:00',
                'expected_end'        => '2026-11-04 17:00:00',
                'status'              => 'brouillon',
                'permit_reference'    => null,
                'permit_validated'    => false,
            ],

            // ── NOVEMBER 2026 ─────────────────────────────────────────────────
            [
                'process'             => 'Maintenance préventive annuelle équipements',
                'provider'            => 'TechServ Madagascar',
                'provider_email'      => 'contact@techserv.mg',
                'project_description' => 'Campagne de maintenance préventive annuelle sur 15 sites (nettoyage, mesures, firmware).',
                'site_code'           => 'MJN-020',
                'site_number'         => '20016',
                'site_name'           => 'Mahajanga Sud',
                'region'              => 'Boeny',
                'theoretical_start'   => '2026-11-03 08:00:00',
                'expected_start'      => '2026-11-04 08:00:00',
                'expected_end'        => '2026-11-20 17:00:00',
                'status'              => 'planifie',
                'permit_reference'    => 'PERM-2026-035',
                'permit_validated'    => false,
            ],
            [
                'process'             => 'Remplacement switchs cœur réseau',
                'provider'            => 'NetConfig Experts',
                'provider_email'      => null,
                'project_description' => 'Remplacement des switchs cœur de réseau vieillissants par des équipements 100G.',
                'site_code'           => 'FNR-020',
                'site_number'         => '20017',
                'site_name'           => 'Fianarantsoa Centre',
                'region'              => 'Haute Matsiatra',
                'theoretical_start'   => '2026-11-10 08:00:00',
                'expected_start'      => '2026-11-11 08:00:00',
                'expected_end'        => '2026-11-14 17:00:00',
                'status'              => 'planifie',
                'permit_reference'    => null,
                'permit_validated'    => false,
            ],

            // ── DECEMBER 2026 ─────────────────────────────────────────────────
            [
                'process'             => 'Déploiement antennes 5G pilote',
                'provider'            => 'Nextel Solutions',
                'provider_email'      => 'ops@nextel.mg',
                'project_description' => 'Déploiement pilote de 3 antennes 5G NR pour test de couverture urbaine.',
                'site_code'           => 'TNR-035',
                'site_number'         => '20018',
                'site_name'           => 'Antananarivo 67 Ha',
                'region'              => 'Analamanga',
                'theoretical_start'   => '2026-12-01 08:00:00',
                'expected_start'      => '2026-12-02 08:00:00',
                'expected_end'        => '2026-12-20 17:00:00',
                'status'              => 'brouillon',
                'permit_reference'    => 'PERM-2026-040',
                'permit_validated'    => false,
            ],
            [
                'process'             => 'Bilan annuel infrastructure réseau',
                'provider'            => 'ConsultTech Mada',
                'provider_email'      => 'etudes@consulttech.mg',
                'project_description' => 'Collecte et consolidation des KPI annuels sur l\'ensemble du parc réseau.',
                'site_code'           => 'TNR-040',
                'site_number'         => '20019',
                'site_name'           => 'Antananarivo Siège',
                'region'              => 'Analamanga',
                'theoretical_start'   => '2026-12-10 08:00:00',
                'expected_start'      => '2026-12-11 08:00:00',
                'expected_end'        => '2026-12-15 17:00:00',
                'status'              => 'planifie',
                'permit_reference'    => null,
                'permit_validated'    => false,
            ],

            // ── JANUARY 2027 ──────────────────────────────────────────────────
            [
                'process'             => 'Déploiement infrastructure 2027',
                'provider'            => 'TechServ Madagascar',
                'provider_email'      => 'contact@techserv.mg',
                'project_description' => 'Lancement du programme pluriannuel d\'extension et modernisation du réseau national.',
                'site_code'           => 'TNR-050',
                'site_number'         => '20020',
                'site_name'           => 'Antananarivo Hub',
                'region'              => 'Analamanga',
                'theoretical_start'   => '2027-01-05 08:00:00',
                'expected_start'      => '2027-01-06 08:00:00',
                'expected_end'        => '2027-01-30 17:00:00',
                'status'              => 'brouillon',
                'permit_reference'    => null,
                'permit_validated'    => false,
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
        $this->addSql("DELETE FROM activity_planning WHERE site_number IN (
            '20001','20002','20003','20004','20005','20006','20007','20008',
            '20009','20010','20011','20012','20013','20014','20015','20016',
            '20017','20018','20019','20020'
        )");
    }
}
