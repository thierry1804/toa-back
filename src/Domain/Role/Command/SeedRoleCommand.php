<?php

declare(strict_types=1);

namespace App\Domain\Role\Command;

use App\Domain\Role\Entity\Role;
use App\Domain\Role\Repository\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:role:seed',
    description: 'Insère les rôles système initiaux',
)]
class SeedRoleCommand extends Command
{
    private const SYSTEM_ROLES = [
        ['name' => 'ROLE_SUPER_ADMIN',   'label' => 'Super Administrateur'],
        ['name' => 'ROLE_ADMIN',         'label' => 'Administrateur'],
        ['name' => 'ROLE_DIRECTION',     'label' => 'Direction'],
        ['name' => 'ROLE_CHEF_PROJET',   'label' => 'Chef de Projet'],
        ['name' => 'ROLE_HSE',           'label' => 'HSE'],
        ['name' => 'ROLE_COLLABORATEUR', 'label' => 'Collaborateur'],
        ['name' => 'ROLE_PRESTATAIRE',   'label' => 'Prestataire'],
        ['name' => 'ROLE_AGENT_TERRAIN', 'label' => 'Agent de terrain'],
    ];

    public function __construct(private EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var RoleRepository $repo */
        $repo = $this->entityManager->getRepository(Role::class);

        $created = 0;
        $skipped = 0;

        foreach (self::SYSTEM_ROLES as $def) {
            if ($repo->findByName($def['name']) !== null) {
                $io->note(sprintf('Rôle "%s" existe déjà, ignoré.', $def['name']));
                ++$skipped;
                continue;
            }

            $role = new Role();
            $role->setName($def['name']);
            $role->setLabel($def['label']);
            $role->setIsSystem(true);

            $this->entityManager->persist($role);
            ++$created;
            $io->writeln(sprintf('  ✓ <info>%s</info> (%s)', $def['name'], $def['label']));
        }

        $this->entityManager->flush();
        $io->success(sprintf('Terminé : %d rôles créés, %d ignorés.', $created, $skipped));

        return Command::SUCCESS;
    }
}
