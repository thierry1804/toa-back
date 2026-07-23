<?php

declare(strict_types=1);

namespace App\Domain\Menu\Command;

use App\Domain\Menu\Entity\Menu;
use App\Domain\Menu\Entity\MenuAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:menu:seed',
    description: 'Insère les menus racines avec leurs règles d\'accès par rôle',
)]
class SeedMenuCommand extends Command
{
    /**
     * Each entry accepts:
     *   - 'public' => true  : no MenuAccess records; visible to all authenticated users
     *   - 'roles'  => [role => [view, create, edit, delete]]  : explicit per-role permissions
     *   - (nothing): defaults to ROLE_SUPER_ADMIN with full access
     *
     * Omitted permission flags default to true (opt-in model for explicit entries).
     */
    private const MENUS = [
        [
            'name' => 'Dashboard',
            'icon' => 'LayoutDashboard',
            'route' => '/dashboard',
            'position' => 1,
            'public' => true,
        ],
        [
            'name' => 'Planification',
            'icon' => 'Calendar',
            'route' => '/planning',
            'position' => 2,
            'roles' => [
                'ROLE_SUPER_ADMIN' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => true],
                'ROLE_CHEF_PROJET' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => true],
            ],
        ],
        [
            'name' => 'Plans de Prévention',
            'icon' => 'Shield',
            'route' => '/prevention',
            'position' => 3,
            'roles' => [
                'ROLE_SUPER_ADMIN'  => ['view' => true,  'create' => true,  'edit' => true,  'delete' => true],
                'ROLE_ADMIN'        => ['view' => true,  'create' => true,  'edit' => true,  'delete' => true],
                'ROLE_HSE'          => ['view' => true,  'create' => false, 'edit' => true,  'delete' => true],
                'ROLE_CHEF_PROJET'  => ['view' => true,  'create' => false, 'edit' => true,  'delete' => false],
                'ROLE_PRESTATAIRE'  => ['view' => true,  'create' => true,  'edit' => false, 'delete' => false],
            ],
        ],
        [
            'name' => 'Permis de Travail',
            'icon' => 'FileText',
            'route' => '/permits-travail',
            'position' => 4,
            'roles' => [
                'ROLE_SUPER_ADMIN' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => true],
                'ROLE_HSE' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => true],
                'ROLE_CHEF_PROJET' => ['view' => true, 'create' => false, 'edit' => true, 'delete' => false],
                'ROLE_COLLABORATEUR' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false],
                'ROLE_PRESTATAIRE' => ['view' => true, 'create' => true, 'edit' => false, 'delete' => false],
            ],
        ],
        [
            'name'        => 'Suivi Permis de Travail',
            'icon'        => 'BarChart2',
            'route'       => '/permits-travail/suivi',
            'position'    => 1,
            'parentRoute' => '/permits-travail',
            'roles'       => [
                'ROLE_SUPER_ADMIN' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false],
                'ROLE_ADMIN'       => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false],
                'ROLE_HSE'         => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false],
            ],
        ],
        [
            'name' => 'Interventions',
            'icon' => 'Clipboard',
            'route' => '/interventions',
            'position' => 5,
            'roles' => [
                'ROLE_SUPER_ADMIN'  => ['view' => true, 'create' => true, 'edit' => true, 'delete' => true],
                'ROLE_PRESTATAIRE'  => ['view' => true, 'create' => true, 'edit' => true, 'delete' => false],
            ],
        ],
        [
            'name'        => 'Suivis journaliers',
            'icon'        => 'ClipboardList',
            'route'       => '/interventions/suivis',
            'position'    => 1,
            'parentRoute' => '/interventions',
            'roles'       => [
                'ROLE_SUPER_ADMIN' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false],
                'ROLE_PRESTATAIRE' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false],
            ],
        ],
        [
            'name'        => 'Suivi Avancements Interventions',
            'icon'        => 'BarChart',
            'route'       => '/interventions/suivi-hse',
            'position'    => 2,
            'parentRoute' => '/interventions',
            'roles'       => [
                'ROLE_SUPER_ADMIN' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false],
                'ROLE_HSE'         => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false],
                'ROLE_CHEF_PROJET' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false],
            ],
        ],
        [
            'name' => 'Utilisateurs',
            'icon' => 'Users',
            'route' => '/users',
            'position' => 6,
            'roles' => [
                'ROLE_SUPER_ADMIN' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => true],
                'ROLE_HSE' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false],
                'ROLE_CHEF_PROJET' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false],
                'ROLE_COLLABORATEUR' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false],
                'ROLE_PRESTATAIRE' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false],
            ],
        ],
        [
            'name' => 'Gestion des accès',
            'icon' => 'menu',
            'route' => '/menu-manager',
            'position' => 7,
        ],
        [
            'name'     => 'Gestion des rôles',
            'icon'     => 'ShieldCheck',
            'route'    => '/roles',
            'position' => 8,
        ],
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Supprime et recrée tous les menus');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');

        if ($force) {
            $this->entityManager->createQuery('DELETE FROM ' . MenuAccess::class)->execute();
            $this->entityManager->createQuery('DELETE FROM ' . Menu::class)->execute();
            $io->writeln('  <comment>Menus existants supprimés.</comment>');
        }

        $menuRepo = $this->entityManager->getRepository(Menu::class);

        $created = 0;
        $skipped = 0;

        foreach (self::MENUS as $definition) {
            $existing = $menuRepo->findOneBy(['name' => $definition['name']]);
            if (null !== $existing) {
                if (isset($definition['parentRoute']) && $existing->getParent() === null) {
                    $parent = $menuRepo->findOneBy(['route' => $definition['parentRoute']]);
                    if ($parent !== null) {
                        $existing->setParent($parent);
                        $this->entityManager->flush();
                        $io->writeln(sprintf('  ✓ <info>%s</info> parent corrigé → %s', $definition['name'], $definition['parentRoute']));
                    }
                }
                $io->note(sprintf('Menu "%s" existe déjà, ignoré.', $definition['name']));
                ++$skipped;
                continue;
            }

            $menu = new Menu();
            $menu->setName($definition['name']);
            $menu->setIcon($definition['icon']);
            $menu->setRoute($definition['route']);
            $menu->setPosition($definition['position']);
            $menu->setIsActive(true);

            if (isset($definition['parentRoute'])) {
                $parent = $menuRepo->findOneBy(['route' => $definition['parentRoute']]);
                if ($parent !== null) {
                    $menu->setParent($parent);
                }
            }

            $this->entityManager->persist($menu);
            $this->entityManager->flush();

            $isPublic = $definition['public'] ?? false;
            $rolesConfig = $definition['roles'] ?? ['ROLE_SUPER_ADMIN' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => true]];

            if (!$isPublic) {
                foreach ($rolesConfig as $roleName => $permissions) {
                    $access = new MenuAccess();
                    $access->setMenu($menu);
                    $access->setRole($roleName);
                    $access->setCanView($permissions['view'] ?? true);
                    $access->setCanCreate($permissions['create'] ?? true);
                    $access->setCanEdit($permissions['edit'] ?? true);
                    $access->setCanDelete($permissions['delete'] ?? true);

                    $this->entityManager->persist($access);
                    $this->entityManager->flush();
                }
            }

            $roleList = $isPublic ? 'public' : implode(', ', array_keys($rolesConfig));
            $io->writeln(sprintf('  ✓ <info>%s</info> (%s).', $definition['name'], $roleList));
            ++$created;
        }

        $io->success(sprintf('Terminé : %d menus créés, %d ignorés.', $created, $skipped));

        return Command::SUCCESS;
    }
}
