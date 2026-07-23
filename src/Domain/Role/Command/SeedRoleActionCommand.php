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
        // intervention.suivi.dashboard
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'intervention.suivi.dashboard', 'bypass' => true],
        ['role' => 'ROLE_HSE',          'key' => 'intervention.suivi.dashboard', 'bypass' => true],
        ['role' => 'ROLE_CHEF_PROJET',  'key' => 'intervention.suivi.dashboard', 'bypass' => true],
        // intervention.suivi.detail
        ['role' => 'ROLE_SUPER_ADMIN',  'key' => 'intervention.suivi.detail', 'bypass' => true],
        ['role' => 'ROLE_HSE',          'key' => 'intervention.suivi.detail', 'bypass' => true],
        ['role' => 'ROLE_CHEF_PROJET',  'key' => 'intervention.suivi.detail', 'bypass' => true],
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
