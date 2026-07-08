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
