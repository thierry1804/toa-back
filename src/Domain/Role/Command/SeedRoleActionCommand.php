<?php

declare(strict_types=1);

namespace App\Domain\Role\Command;

use App\Domain\Role\Entity\ActionKey;
use App\Domain\Role\Entity\RoleAction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:role-action:seed',
    description: 'Insère les ActionKey et RoleAction initiaux (miroir du comportement hardcodé)',
)]
class SeedRoleActionCommand extends Command
{
    private const ACTION_KEYS = [
        [
            'key'            => 'plan_prevention.examine',
            'label'          => 'Examiner un plan de prévention',
            'module'         => 'plan_prevention',
            'ownershipField' => 'chef_projet',
        ],
        [
            'key'            => 'plan_prevention.valider_hse',
            'label'          => 'Valider un plan de prévention (HSE)',
            'module'         => 'plan_prevention',
            'ownershipField' => null,
        ],
        [
            'key'            => 'plan_prevention.refuser_hse',
            'label'          => 'Refuser un plan de prévention (HSE)',
            'module'         => 'plan_prevention',
            'ownershipField' => null,
        ],
        [
            'key'            => 'plan_prevention.submit',
            'label'          => 'Soumettre un plan de prévention',
            'module'         => 'plan_prevention',
            'ownershipField' => 'created_by',
        ],
        [
            'key'            => 'plan_prevention.resoumettre',
            'label'          => 'Resoumettre un plan de prévention',
            'module'         => 'plan_prevention',
            'ownershipField' => 'created_by',
        ],
        [
            'key'            => 'plan_prevention.import_kmz',
            'label'          => 'Importer un fichier KMZ (plan de prévention)',
            'module'         => 'plan_prevention',
            'ownershipField' => 'chef_projet',
        ],
        // permit_travail
        [
            'key'            => 'permit_travail.view',
            'label'          => 'Consulter un permis de travail',
            'module'         => 'permit_travail',
            'ownershipField' => 'created_by',
        ],
        [
            'key'            => 'permit_travail.create',
            'label'          => 'Créer un permis de travail',
            'module'         => 'permit_travail',
            'ownershipField' => null,
        ],
        [
            'key'            => 'permit_travail.edit',
            'label'          => 'Modifier un permis de travail (statut BROUILLON)',
            'module'         => 'permit_travail',
            'ownershipField' => 'created_by',
        ],
        [
            'key'            => 'permit_travail.submit',
            'label'          => 'Soumettre un permis de travail',
            'module'         => 'permit_travail',
            'ownershipField' => 'created_by',
        ],
        [
            'key'            => 'permit_travail.valider_hse',
            'label'          => 'Valider un permis de travail (HSE)',
            'module'         => 'permit_travail',
            'ownershipField' => null,
        ],
        [
            'key'            => 'permit_travail.refuser_hse',
            'label'          => 'Refuser un permis de travail (HSE)',
            'module'         => 'permit_travail',
            'ownershipField' => null,
        ],
        [
            'key'            => 'permit_travail.generate_pdf',
            'label'          => 'Générer le PDF officiel d\'un permis de travail validé',
            'module'         => 'permit_travail',
            'ownershipField' => null,
        ],
        [
            'key'            => 'permit_travail.resoumettre',
            'label'          => 'Resoumettre un permis de travail refusé',
            'module'         => 'permit_travail',
            'ownershipField' => 'created_by',
        ],
        [
            'key'            => 'permit_travail.suivi',
            'label'          => 'Tableau de bord suivi des permis de travail',
            'module'         => 'permit_travail',
            'ownershipField' => null,
        ],
        [
            'key'            => 'permit_travail.logs',
            'label'          => 'Consulter les logs journaliers d\'un permis de travail',
            'module'         => 'permit_travail',
            'ownershipField' => null,
        ],
        // intervention
        [
            'key'            => 'intervention.create',
            'label'          => 'Créer une intervention',
            'module'         => 'intervention',
            'ownershipField' => null,
        ],
        [
            'key'            => 'intervention.view',
            'label'          => 'Consulter une intervention',
            'module'         => 'intervention',
            'ownershipField' => 'created_by',
        ],
        [
            'key'            => 'intervention.edit',
            'label'          => 'Modifier une évaluation de risque (statut EN_PREPARATION)',
            'module'         => 'intervention',
            'ownershipField' => 'created_by',
        ],
        [
            'key'            => 'intervention.valider_evaluation',
            'label'          => 'Valider l\'évaluation des risques avant intervention',
            'module'         => 'intervention',
            'ownershipField' => 'created_by',
        ],
        // suivi_journalier
        [
            'key'            => 'suivi_journalier.create',
            'label'          => 'Créer un suivi journalier d\'intervention',
            'module'         => 'suivi_journalier',
            'ownershipField' => null,
        ],
        [
            'key'            => 'suivi_journalier.view',
            'label'          => 'Consulter les suivis journaliers',
            'module'         => 'suivi_journalier',
            'ownershipField' => 'created_by',
        ],
        [
            'key'            => 'suivi_journalier.edit',
            'label'          => 'Modifier un suivi journalier (jour J uniquement)',
            'module'         => 'suivi_journalier',
            'ownershipField' => 'created_by',
        ],
        [
            'key'            => 'suivi_journalier.delete_document',
            'label'          => 'Supprimer un document de suivi journalier',
            'module'         => 'suivi_journalier',
            'ownershipField' => 'created_by',
        ],
        // take5_record
        [
            'key'            => 'take5_record.create',
            'label'          => 'Créer un Take 5',
            'module'         => 'take5_record',
            'ownershipField' => null,
        ],
        [
            'key'            => 'take5_record.view',
            'label'          => 'Consulter les Take 5',
            'module'         => 'take5_record',
            'ownershipField' => 'created_by',
        ],
        // controle_journalier
        [
            'key'            => 'controle_journalier.create',
            'label'          => 'Créer un contrôle journalier (commencement des travaux)',
            'module'         => 'controle_journalier',
            'ownershipField' => null,
        ],
        [
            'key'            => 'controle_journalier.view',
            'label'          => 'Consulter les contrôles journaliers',
            'module'         => 'controle_journalier',
            'ownershipField' => 'created_by',
        ],
        [
            'key'            => 'controle_journalier.edit',
            'label'          => 'Compléter la clôture d\'un contrôle journalier (jour J uniquement)',
            'module'         => 'controle_journalier',
            'ownershipField' => 'created_by',
        ],
        [
            'key'            => 'intervention.suivi.dashboard',
            'label'          => 'Tableau de bord suivi avancements interventions (HSE/Chef de Projet)',
            'module'         => 'intervention',
            'ownershipField' => null,
        ],
        [
            'key'            => 'intervention.suivi.detail',
            'label'          => 'Détail suivi intervention (lecture seule HSE/Chef de Projet)',
            'module'         => 'intervention',
            'ownershipField' => null,
        ],
        // cloture
        [
            'key'            => 'permit_travail.cloturer',
            'label'          => 'Clôturer un permis de travail',
            'module'         => 'permit_travail',
            'ownershipField' => 'created_by',
        ],
        // validation CDP
        [
            'key'            => 'permit_travail.pv_valider',
            'label'          => 'Valider le PV de réception (Chef de Projet)',
            'module'         => 'permit_travail',
            'ownershipField' => 'plan_prevention.chef_projet',
        ],
        [
            'key'            => 'permit_travail.pv_refuser',
            'label'          => 'Refuser le PV de réception (Chef de Projet)',
            'module'         => 'permit_travail',
            'ownershipField' => 'plan_prevention.chef_projet',
        ],
        [
            'key'            => 'permit_travail.delete',
            'label'          => 'Supprimer un permis de travail (statut BROUILLON)',
            'module'         => 'permit_travail',
            'ownershipField' => 'created_by',
        ],
        // dashboard
        [
            'key'            => 'dashboard.kpis.view',
            'label'          => 'Tableau de bord KPIs HSE',
            'module'         => 'dashboard',
            'ownershipField' => null,
        ],
        // installation_equipement
        [
            'key'            => 'installation_equipement.view',
            'label'          => 'Consulter les installations et équipements',
            'module'         => 'referentiel',
            'ownershipField' => null,
        ],
        [
            'key'            => 'installation_equipement.create',
            'label'          => 'Créer une installation/équipement',
            'module'         => 'referentiel',
            'ownershipField' => null,
        ],
        [
            'key'            => 'installation_equipement.edit',
            'label'          => 'Modifier une installation/équipement',
            'module'         => 'referentiel',
            'ownershipField' => null,
        ],
        [
            'key'            => 'installation_equipement.delete',
            'label'          => 'Supprimer une installation/équipement',
            'module'         => 'referentiel',
            'ownershipField' => null,
        ],
        [
            'key'            => 'user.upload_signature',
            'label'          => 'Uploader la signature électronique d\'un utilisateur',
            'module'         => 'user',
            'ownershipField' => 'self',
        ],
    ];

    private const ROLE_ACTIONS = [
        // plan_prevention.examine
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'plan_prevention.examine',     'bypass' => true],
        ['role' => 'ROLE_ADMIN',        'key' => 'plan_prevention.examine',     'bypass' => true],
        ['role' => 'ROLE_CHEF_PROJET',  'key' => 'plan_prevention.examine',     'bypass' => false],
        // plan_prevention.valider_hse
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'plan_prevention.valider_hse', 'bypass' => true],
        ['role' => 'ROLE_ADMIN',        'key' => 'plan_prevention.valider_hse', 'bypass' => true],
        ['role' => 'ROLE_HSE',          'key' => 'plan_prevention.valider_hse', 'bypass' => true],
        // plan_prevention.refuser_hse
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'plan_prevention.refuser_hse', 'bypass' => true],
        ['role' => 'ROLE_ADMIN',        'key' => 'plan_prevention.refuser_hse', 'bypass' => true],
        ['role' => 'ROLE_HSE',          'key' => 'plan_prevention.refuser_hse', 'bypass' => true],
        // plan_prevention.submit
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'plan_prevention.submit',      'bypass' => true],
        ['role' => 'ROLE_PRESTATAIRE',  'key' => 'plan_prevention.submit',      'bypass' => false],
        // plan_prevention.resoumettre
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'plan_prevention.resoumettre', 'bypass' => true],
        ['role' => 'ROLE_PRESTATAIRE',  'key' => 'plan_prevention.resoumettre', 'bypass' => false],
        // plan_prevention.import_kmz
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'plan_prevention.import_kmz',  'bypass' => true],
        ['role' => 'ROLE_ADMIN',        'key' => 'plan_prevention.import_kmz',  'bypass' => true],
        ['role' => 'ROLE_CHEF_PROJET',  'key' => 'plan_prevention.import_kmz',  'bypass' => false],
        ['role' => 'ROLE_PRESTATAIRE',  'key' => 'plan_prevention.import_kmz',  'bypass' => false],
        // permit_travail.view
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'permit_travail.view',   'bypass' => true],
        ['role' => 'ROLE_HSE',          'key' => 'permit_travail.view',   'bypass' => true],
        ['role' => 'ROLE_CHEF_PROJET',  'key' => 'permit_travail.view',   'bypass' => true],
        ['role' => 'ROLE_COLLABORATEUR', 'key' => 'permit_travail.view',  'bypass' => true],
        ['role' => 'ROLE_PRESTATAIRE',  'key' => 'permit_travail.view',   'bypass' => false],
        // permit_travail.create
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'permit_travail.create', 'bypass' => true],
        ['role' => 'ROLE_HSE',          'key' => 'permit_travail.create', 'bypass' => true],
        ['role' => 'ROLE_PRESTATAIRE',  'key' => 'permit_travail.create', 'bypass' => true],
        // permit_travail.edit
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'permit_travail.edit',   'bypass' => true],
        ['role' => 'ROLE_HSE',          'key' => 'permit_travail.edit',   'bypass' => true],
        ['role' => 'ROLE_PRESTATAIRE',  'key' => 'permit_travail.edit',   'bypass' => false],
        // permit_travail.submit
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'permit_travail.submit',      'bypass' => true],
        ['role' => 'ROLE_HSE',          'key' => 'permit_travail.submit',      'bypass' => true],
        ['role' => 'ROLE_PRESTATAIRE',  'key' => 'permit_travail.submit',      'bypass' => false],
        // permit_travail.valider_hse
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'permit_travail.valider_hse', 'bypass' => true],
        ['role' => 'ROLE_ADMIN',        'key' => 'permit_travail.valider_hse', 'bypass' => true],
        ['role' => 'ROLE_HSE',          'key' => 'permit_travail.valider_hse', 'bypass' => true],
        // permit_travail.refuser_hse
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'permit_travail.refuser_hse', 'bypass' => true],
        ['role' => 'ROLE_ADMIN',        'key' => 'permit_travail.refuser_hse', 'bypass' => true],
        ['role' => 'ROLE_HSE',          'key' => 'permit_travail.refuser_hse', 'bypass' => true],
        // permit_travail.generate_pdf
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'permit_travail.generate_pdf', 'bypass' => true],
        ['role' => 'ROLE_ADMIN',        'key' => 'permit_travail.generate_pdf', 'bypass' => true],
        ['role' => 'ROLE_HSE',          'key' => 'permit_travail.generate_pdf', 'bypass' => true],
        // permit_travail.resoumettre
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'permit_travail.resoumettre', 'bypass' => true],
        ['role' => 'ROLE_PRESTATAIRE',  'key' => 'permit_travail.resoumettre', 'bypass' => false],
        // permit_travail.suivi
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'permit_travail.suivi', 'bypass' => true],
        ['role' => 'ROLE_ADMIN',        'key' => 'permit_travail.suivi', 'bypass' => true],
        ['role' => 'ROLE_HSE',          'key' => 'permit_travail.suivi', 'bypass' => true],
        // permit_travail.logs
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'permit_travail.logs', 'bypass' => true],
        ['role' => 'ROLE_ADMIN',        'key' => 'permit_travail.logs', 'bypass' => true],
        ['role' => 'ROLE_HSE',          'key' => 'permit_travail.logs', 'bypass' => true],
        // intervention.create
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'intervention.create',             'bypass' => true],
        ['role' => 'ROLE_PRESTATAIRE',  'key' => 'intervention.create',             'bypass' => false],
        // intervention.view
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'intervention.view',               'bypass' => true],
        ['role' => 'ROLE_PRESTATAIRE',  'key' => 'intervention.view',               'bypass' => false],
        // intervention.edit
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'intervention.edit',               'bypass' => true],
        ['role' => 'ROLE_PRESTATAIRE',  'key' => 'intervention.edit',               'bypass' => false],
        // intervention.valider_evaluation
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'intervention.valider_evaluation', 'bypass' => true],
        ['role' => 'ROLE_PRESTATAIRE',  'key' => 'intervention.valider_evaluation', 'bypass' => false],
        // suivi_journalier.create
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'suivi_journalier.create',          'bypass' => true],
        ['role' => 'ROLE_PRESTATAIRE',  'key' => 'suivi_journalier.create',          'bypass' => false],
        // suivi_journalier.view
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'suivi_journalier.view',            'bypass' => true],
        ['role' => 'ROLE_ADMIN',        'key' => 'suivi_journalier.view',            'bypass' => true],
        ['role' => 'ROLE_HSE',          'key' => 'suivi_journalier.view',            'bypass' => true],
        ['role' => 'ROLE_PRESTATAIRE',  'key' => 'suivi_journalier.view',            'bypass' => false],
        // suivi_journalier.edit
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'suivi_journalier.edit',            'bypass' => true],
        ['role' => 'ROLE_PRESTATAIRE',  'key' => 'suivi_journalier.edit',            'bypass' => false],
        // suivi_journalier.delete_document
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'suivi_journalier.delete_document', 'bypass' => true],
        ['role' => 'ROLE_PRESTATAIRE',  'key' => 'suivi_journalier.delete_document', 'bypass' => false],
        // take5_record.create
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'take5_record.create', 'bypass' => true],
        ['role' => 'ROLE_PRESTATAIRE',  'key' => 'take5_record.create', 'bypass' => false],
        // take5_record.view
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'take5_record.view', 'bypass' => true],
        ['role' => 'ROLE_ADMIN',        'key' => 'take5_record.view', 'bypass' => true],
        ['role' => 'ROLE_HSE',          'key' => 'take5_record.view', 'bypass' => true],
        ['role' => 'ROLE_PRESTATAIRE',  'key' => 'take5_record.view', 'bypass' => false],
        // controle_journalier.create
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'controle_journalier.create', 'bypass' => true],
        ['role' => 'ROLE_PRESTATAIRE',  'key' => 'controle_journalier.create', 'bypass' => false],
        // controle_journalier.view
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'controle_journalier.view', 'bypass' => true],
        ['role' => 'ROLE_ADMIN',        'key' => 'controle_journalier.view', 'bypass' => true],
        ['role' => 'ROLE_HSE',          'key' => 'controle_journalier.view', 'bypass' => true],
        ['role' => 'ROLE_PRESTATAIRE',  'key' => 'controle_journalier.view', 'bypass' => false],
        // controle_journalier.edit
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'controle_journalier.edit', 'bypass' => true],
        ['role' => 'ROLE_PRESTATAIRE',  'key' => 'controle_journalier.edit', 'bypass' => false],
        // intervention.suivi.dashboard
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'intervention.suivi.dashboard', 'bypass' => true],
        ['role' => 'ROLE_HSE',          'key' => 'intervention.suivi.dashboard', 'bypass' => true],
        ['role' => 'ROLE_CHEF_PROJET',  'key' => 'intervention.suivi.dashboard', 'bypass' => true],
        // intervention.suivi.detail
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'intervention.suivi.detail', 'bypass' => true],
        ['role' => 'ROLE_HSE',          'key' => 'intervention.suivi.detail', 'bypass' => true],
        ['role' => 'ROLE_CHEF_PROJET',  'key' => 'intervention.suivi.detail', 'bypass' => true],
        // permit_travail.cloturer
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'permit_travail.cloturer', 'bypass' => true],
        ['role' => 'ROLE_HSE',          'key' => 'permit_travail.cloturer', 'bypass' => true],
        ['role' => 'ROLE_PRESTATAIRE',  'key' => 'permit_travail.cloturer', 'bypass' => false],
        // permit_travail.pv_valider
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'permit_travail.pv_valider', 'bypass' => true],
        ['role' => 'ROLE_CHEF_PROJET',  'key' => 'permit_travail.pv_valider', 'bypass' => false],
        // permit_travail.pv_refuser
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'permit_travail.pv_refuser', 'bypass' => true],
        ['role' => 'ROLE_CHEF_PROJET',  'key' => 'permit_travail.pv_refuser', 'bypass' => false],
        // permit_travail.delete
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'permit_travail.delete', 'bypass' => true],
        ['role' => 'ROLE_HSE',          'key' => 'permit_travail.delete', 'bypass' => true],
        ['role' => 'ROLE_PRESTATAIRE',  'key' => 'permit_travail.delete', 'bypass' => false],
        // dashboard.kpis.view
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'dashboard.kpis.view', 'bypass' => true],
        ['role' => 'ROLE_ADMIN',        'key' => 'dashboard.kpis.view', 'bypass' => true],
        ['role' => 'ROLE_HSE',          'key' => 'dashboard.kpis.view', 'bypass' => true],
        // installation_equipement.view
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'installation_equipement.view',   'bypass' => true],
        ['role' => 'ROLE_HSE',          'key' => 'installation_equipement.view',   'bypass' => true],
        // installation_equipement.create
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'installation_equipement.create', 'bypass' => true],
        ['role' => 'ROLE_HSE',          'key' => 'installation_equipement.create', 'bypass' => true],
        // installation_equipement.edit
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'installation_equipement.edit',   'bypass' => true],
        ['role' => 'ROLE_HSE',          'key' => 'installation_equipement.edit',   'bypass' => true],
        // installation_equipement.delete
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'installation_equipement.delete', 'bypass' => true],
        ['role' => 'ROLE_HSE',          'key' => 'installation_equipement.delete', 'bypass' => true],
        // user.upload_signature
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'user.upload_signature', 'bypass' => true],
        ['role' => 'ROLE_ADMIN',        'key' => 'user.upload_signature', 'bypass' => true],
        ['role' => 'ROLE_HSE',          'key' => 'user.upload_signature', 'bypass' => false],
        ['role' => 'ROLE_PRESTATAIRE',  'key' => 'user.upload_signature', 'bypass' => false],
        ['role' => 'ROLE_AGENT_TERRAIN', 'key' => 'user.upload_signature', 'bypass' => false],
        // ROLE_AGENT_TERRAIN : mêmes droits de travail terrain qu'un
        // prestataire (permis, interventions, suivis), sans la gestion du
        // dossier (submit/resoumettre plan de prévention, import KMZ,
        // clôture de permis) qui reste réservée au compte prestataire
        // propriétaire de l'entreprise.
        ['role' => 'ROLE_AGENT_TERRAIN', 'key' => 'permit_travail.view',              'bypass' => false],
        ['role' => 'ROLE_AGENT_TERRAIN', 'key' => 'permit_travail.create',            'bypass' => false],
        ['role' => 'ROLE_AGENT_TERRAIN', 'key' => 'permit_travail.edit',              'bypass' => false],
        ['role' => 'ROLE_AGENT_TERRAIN', 'key' => 'permit_travail.delete',            'bypass' => false],
        ['role' => 'ROLE_AGENT_TERRAIN', 'key' => 'intervention.create',              'bypass' => false],
        ['role' => 'ROLE_AGENT_TERRAIN', 'key' => 'intervention.view',                'bypass' => false],
        ['role' => 'ROLE_AGENT_TERRAIN', 'key' => 'intervention.edit',                'bypass' => false],
        ['role' => 'ROLE_AGENT_TERRAIN', 'key' => 'intervention.valider_evaluation',  'bypass' => false],
        ['role' => 'ROLE_AGENT_TERRAIN', 'key' => 'suivi_journalier.create',          'bypass' => false],
        ['role' => 'ROLE_AGENT_TERRAIN', 'key' => 'suivi_journalier.view',            'bypass' => false],
        ['role' => 'ROLE_AGENT_TERRAIN', 'key' => 'suivi_journalier.edit',            'bypass' => false],
        ['role' => 'ROLE_AGENT_TERRAIN', 'key' => 'suivi_journalier.delete_document', 'bypass' => false],
        ['role' => 'ROLE_AGENT_TERRAIN', 'key' => 'take5_record.create',              'bypass' => false],
        ['role' => 'ROLE_AGENT_TERRAIN', 'key' => 'take5_record.view',                'bypass' => false],
        ['role' => 'ROLE_AGENT_TERRAIN', 'key' => 'controle_journalier.create',       'bypass' => false],
        ['role' => 'ROLE_AGENT_TERRAIN', 'key' => 'controle_journalier.view',         'bypass' => false],
        ['role' => 'ROLE_AGENT_TERRAIN', 'key' => 'controle_journalier.edit',         'bypass' => false],
    ];

    public function __construct(private EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $akRepo = $this->entityManager->getRepository(ActionKey::class);
        $raRepo = $this->entityManager->getRepository(RoleAction::class);

        $io->section('ActionKeys');
        foreach (self::ACTION_KEYS as $def) {
            if ($akRepo->findByKey($def['key']) !== null) {
                $io->note(sprintf('ActionKey "%s" existe déjà, ignoré.', $def['key']));
                continue;
            }

            $ak = new ActionKey();
            $ak->setKey($def['key']);
            $ak->setLabel($def['label']);
            $ak->setModule($def['module']);
            $ak->setOwnershipField($def['ownershipField']);

            $this->entityManager->persist($ak);
            $io->writeln(sprintf('  ✓ <info>%s</info>', $def['key']));
        }

        $this->entityManager->flush();

        $io->section('RoleActions');
        foreach (self::ROLE_ACTIONS as $def) {
            $existing = $raRepo->findOneBy(['roleName' => $def['role'], 'actionKey' => $def['key']]);
            if ($existing !== null) {
                $io->note(sprintf('RoleAction "%s → %s" existe déjà, ignoré.', $def['role'], $def['key']));
                continue;
            }

            $ra = new RoleAction();
            $ra->setRoleName($def['role']);
            $ra->setActionKey($def['key']);
            $ra->setBypassOwnership($def['bypass']);

            $this->entityManager->persist($ra);
            $io->writeln(sprintf('  ✓ <info>%s</info> → %s (bypass=%s)', $def['role'], $def['key'], $def['bypass'] ? 'true' : 'false'));
        }

        $this->entityManager->flush();
        $io->success('Seed terminé.');

        return Command::SUCCESS;
    }
}
