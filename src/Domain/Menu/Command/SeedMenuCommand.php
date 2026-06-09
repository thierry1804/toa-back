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
    description: 'Insère les menus racines avec accès complet pour ROLE_SUPER_ADMIN',
)]
class SeedMenuCommand extends Command
{
    private const MENUS = [
        ['name' => 'Dashboard',                'icon' => 'LayoutDashboard', 'route' => '/dashboard',      'position' => 1,  'public' => true],
        ['name' => 'Planification',            'icon' => 'Calendar',        'route' => '/planification',  'position' => 2],
        ['name' => 'Plans de Prévention',      'icon' => 'Shield',          'route' => '/prevention',     'position' => 3],
        ['name' => 'Permis de Travail',        'icon' => 'FileText',        'route' => '/permits',        'position' => 4],
        ['name' => 'Interventions',            'icon' => 'Clipboard',       'route' => '/interventions',  'position' => 5],
        ['name' => 'Utilisateurs',             'icon' => 'Users',           'route' => '/users',          'position' => 6],
        ['name' => 'Gestion Menu',             'icon' => 'menu',            'route' => '/menu-manager',   'position' => 7],
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

            $this->entityManager->persist($menu);
            $this->entityManager->flush();

            $isPublic = $definition['public'] ?? false;

            if (!$isPublic) {
                $access = new MenuAccess();
                $access->setMenu($menu);
                $access->setRole('ROLE_SUPER_ADMIN');
                $access->setCanView(true);
                $access->setCanCreate(true);
                $access->setCanEdit(true);
                $access->setCanDelete(true);

                $this->entityManager->persist($access);
                $this->entityManager->flush();
            }

            $io->writeln(sprintf('  ✓ <info>%s</info> %s.', $definition['name'], $isPublic ? 'créé (public)' : 'créé avec accès complet'));
            ++$created;
        }

        $io->success(sprintf('Terminé : %d menus créés, %d ignorés.', $created, $skipped));

        return Command::SUCCESS;
    }
}
