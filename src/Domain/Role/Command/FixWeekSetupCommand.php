<?php

declare(strict_types=1);

namespace App\Domain\Role\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:setup:fix-week',
    description: 'Fix missing action_key/role_action seeds + resync identity sequences',
)]
class FixWeekSetupCommand extends Command
{
    private const ACTION_KEYS = [
        ['key' => 'permit_travail.view',        'label' => 'Consulter un permis de travail',                         'module' => 'permit_travail', 'ownership' => 'created_by'],
        ['key' => 'permit_travail.create',      'label' => 'Créer un permis de travail',                             'module' => 'permit_travail', 'ownership' => null],
        ['key' => 'permit_travail.edit',        'label' => 'Modifier un permis de travail (statut BROUILLON)',        'module' => 'permit_travail', 'ownership' => 'created_by'],
        ['key' => 'permit_travail.submit',      'label' => 'Soumettre un permis de travail',                         'module' => 'permit_travail', 'ownership' => 'created_by'],
        ['key' => 'permit_travail.valider_hse', 'label' => 'Valider un permis de travail (HSE)',                     'module' => 'permit_travail', 'ownership' => null],
        ['key' => 'permit_travail.refuser_hse', 'label' => 'Refuser un permis de travail (HSE)',                     'module' => 'permit_travail', 'ownership' => null],
        ['key' => 'permit_travail.generate_pdf','label' => "Générer le PDF officiel d'un permis de travail validé",  'module' => 'permit_travail', 'ownership' => null],
        ['key' => 'permit_travail.resoumettre', 'label' => 'Resoumettre un permis de travail refusé',                'module' => 'permit_travail', 'ownership' => 'created_by'],
        ['key' => 'permit_travail.suivi',       'label' => 'Tableau de bord suivi des permis de travail',            'module' => 'permit_travail', 'ownership' => null],
        ['key' => 'permit_travail.logs',        'label' => "Consulter les logs journaliers d'un permis de travail",  'module' => 'permit_travail', 'ownership' => null],
        ['key' => 'user.upload_signature',      'label' => 'Uploader sa signature électronique',                     'module' => 'user',           'ownership' => 'id'],
    ];

    private const ROLE_ACTIONS = [
        ['role' => 'ROLE_SUPER_ADMIN', 'key' => 'permit_travail.view',         'bypass' => true],
        ['role' => 'ROLE_HSE',         'key' => 'permit_travail.view',         'bypass' => true],
        ['role' => 'ROLE_CHEF_PROJET', 'key' => 'permit_travail.view',         'bypass' => true],
        ['role' => 'ROLE_PRESTATAIRE', 'key' => 'permit_travail.view',         'bypass' => false],
        ['role' => 'ROLE_SUPER_ADMIN', 'key' => 'permit_travail.create',       'bypass' => true],
        ['role' => 'ROLE_HSE',         'key' => 'permit_travail.create',       'bypass' => true],
        ['role' => 'ROLE_PRESTATAIRE', 'key' => 'permit_travail.create',       'bypass' => true],
        ['role' => 'ROLE_SUPER_ADMIN', 'key' => 'permit_travail.edit',         'bypass' => true],
        ['role' => 'ROLE_HSE',         'key' => 'permit_travail.edit',         'bypass' => true],
        ['role' => 'ROLE_PRESTATAIRE', 'key' => 'permit_travail.edit',         'bypass' => false],
        ['role' => 'ROLE_SUPER_ADMIN', 'key' => 'permit_travail.submit',       'bypass' => true],
        ['role' => 'ROLE_HSE',         'key' => 'permit_travail.submit',       'bypass' => true],
        ['role' => 'ROLE_PRESTATAIRE', 'key' => 'permit_travail.submit',       'bypass' => false],
        ['role' => 'ROLE_SUPER_ADMIN', 'key' => 'permit_travail.valider_hse',  'bypass' => true],
        ['role' => 'ROLE_ADMIN',       'key' => 'permit_travail.valider_hse',  'bypass' => true],
        ['role' => 'ROLE_HSE',         'key' => 'permit_travail.valider_hse',  'bypass' => true],
        ['role' => 'ROLE_SUPER_ADMIN', 'key' => 'permit_travail.refuser_hse',  'bypass' => true],
        ['role' => 'ROLE_ADMIN',       'key' => 'permit_travail.refuser_hse',  'bypass' => true],
        ['role' => 'ROLE_HSE',         'key' => 'permit_travail.refuser_hse',  'bypass' => true],
        ['role' => 'ROLE_SUPER_ADMIN', 'key' => 'permit_travail.generate_pdf', 'bypass' => true],
        ['role' => 'ROLE_ADMIN',       'key' => 'permit_travail.generate_pdf', 'bypass' => true],
        ['role' => 'ROLE_HSE',         'key' => 'permit_travail.generate_pdf', 'bypass' => true],
        ['role' => 'ROLE_SUPER_ADMIN', 'key' => 'permit_travail.resoumettre',  'bypass' => true],
        ['role' => 'ROLE_PRESTATAIRE', 'key' => 'permit_travail.resoumettre',  'bypass' => false],
        ['role' => 'ROLE_SUPER_ADMIN', 'key' => 'permit_travail.suivi',        'bypass' => true],
        ['role' => 'ROLE_ADMIN',       'key' => 'permit_travail.suivi',        'bypass' => true],
        ['role' => 'ROLE_HSE',         'key' => 'permit_travail.suivi',        'bypass' => true],
        ['role' => 'ROLE_SUPER_ADMIN', 'key' => 'permit_travail.logs',         'bypass' => true],
        ['role' => 'ROLE_ADMIN',       'key' => 'permit_travail.logs',         'bypass' => true],
        ['role' => 'ROLE_HSE',         'key' => 'permit_travail.logs',         'bypass' => true],
        ['role' => 'ROLE_PRESTATAIRE', 'key' => 'user.upload_signature',       'bypass' => false],
        ['role' => 'ROLE_ADMIN',       'key' => 'user.upload_signature',       'bypass' => true],
        ['role' => 'ROLE_SUPER_ADMIN', 'key' => 'user.upload_signature',       'bypass' => true],
    ];

    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('TOA Week Fix — sequences + action_key/role_action seed');

        // ── 1. Resync identity sequences ──────────────────────────────────────────
        $io->section('Sequence resync');
        foreach (['action_key', 'role_action'] as $table) {
            $max = (int) $this->connection->fetchOne(sprintf('SELECT COALESCE(MAX(id), 0) FROM "%s"', $table));
            $next = $max + 1;
            $this->connection->executeStatement(
                sprintf('ALTER TABLE "%s" ALTER COLUMN id RESTART WITH %d', $table, $next)
            );
            $io->writeln(sprintf('  ✓ %s sequence restarted at %d', $table, $next));
        }

        // ── 2. action_key seed ────────────────────────────────────────────────────
        $io->section('action_key');
        $inserted = 0;
        $skipped  = 0;
        foreach (self::ACTION_KEYS as $def) {
            $exists = (bool) $this->connection->fetchOne(
                'SELECT 1 FROM "action_key" WHERE key = :key',
                ['key' => $def['key']]
            );
            if ($exists) {
                $io->writeln(sprintf('  – skipped (exists): %s', $def['key']));
                ++$skipped;
                continue;
            }
            $this->connection->executeStatement(
                'INSERT INTO "action_key" (key, label, module, ownership_field) VALUES (:key, :label, :module, :ownership)',
                ['key' => $def['key'], 'label' => $def['label'], 'module' => $def['module'], 'ownership' => $def['ownership']]
            );
            $io->writeln(sprintf('  ✓ inserted: %s', $def['key']));
            ++$inserted;
        }
        $io->writeln(sprintf('  → %d inserted, %d skipped', $inserted, $skipped));

        // ── 3. role_action seed ───────────────────────────────────────────────────
        $io->section('role_action');
        $inserted = 0;
        $skipped  = 0;
        foreach (self::ROLE_ACTIONS as $def) {
            $exists = (bool) $this->connection->fetchOne(
                'SELECT 1 FROM "role_action" WHERE role_name = :role AND action_key = :key',
                ['role' => $def['role'], 'key' => $def['key']]
            );
            if ($exists) {
                ++$skipped;
                continue;
            }
            $this->connection->executeStatement(
                'INSERT INTO "role_action" (role_name, action_key, bypass_ownership) VALUES (:role, :key, :bypass)',
                ['role' => $def['role'], 'key' => $def['key'], 'bypass' => $def['bypass'] ? 'true' : 'false']
            );
            $io->writeln(sprintf('  ✓ %s → %s', $def['role'], $def['key']));
            ++$inserted;
        }
        $io->writeln(sprintf('  → %d inserted, %d skipped', $inserted, $skipped));

        // ── 4. Menu + MenuAccess seed ─────────────────────────────────────────────
        $io->section('menu seed');
        $menuCommand = $this->getApplication()->find('app:menu:seed');
        $menuCommand->run(new ArrayInput([]), $output);

        $io->success('Done.');

        return Command::SUCCESS;
    }
}
