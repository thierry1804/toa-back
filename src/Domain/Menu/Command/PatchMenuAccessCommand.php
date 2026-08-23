<?php

declare(strict_types=1);

namespace App\Domain\Menu\Command;

use App\Domain\Menu\Entity\Menu;
use App\Domain\Menu\Entity\MenuAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:menu:patch-access',
    description: 'Ajoute les règles d\'accès manquantes sans supprimer les menus existants',
)]
class PatchMenuAccessCommand extends Command
{
    private const PATCHES = [
        '/prevention' => [
            'ROLE_SUPER_ADMIN' => ['view' => true,  'create' => true,  'edit' => true,  'delete' => true],
            'ROLE_ADMIN'       => ['view' => true,  'create' => true,  'edit' => true,  'delete' => true],
            'ROLE_HSE'         => ['view' => true,  'create' => false, 'edit' => false, 'delete' => false],
            'ROLE_CHEF_PROJET' => ['view' => true,  'create' => false, 'edit' => false, 'delete' => false],
            'ROLE_PRESTATAIRE' => ['view' => true,  'create' => true,  'edit' => false, 'delete' => false],
        ],
        '/users' => [
            'ROLE_PRESTATAIRE' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => false],
        ],
    ];

    public function __construct(private EntityManagerInterface $em) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $menuRepo = $this->em->getRepository(Menu::class);
        $accessRepo = $this->em->getRepository(MenuAccess::class);

        foreach (self::PATCHES as $route => $roles) {
            $menu = $menuRepo->findOneBy(['route' => $route]);
            if (!$menu instanceof Menu) {
                $io->warning(sprintf('Menu route "%s" introuvable — ignoré.', $route));
                continue;
            }

            foreach ($roles as $role => $perms) {
                $existing = $accessRepo->findOneBy(['menu' => $menu, 'role' => $role]);

                if ($existing instanceof MenuAccess) {
                    $existing->setCanView($perms['view']);
                    $existing->setCanCreate($perms['create']);
                    $existing->setCanEdit($perms['edit']);
                    $existing->setCanDelete($perms['delete']);
                    $io->writeln(sprintf('  ↺ <comment>Mis à jour</comment> %s → %s', $route, $role));
                } else {
                    $access = new MenuAccess();
                    $access->setMenu($menu);
                    $access->setRole($role);
                    $access->setCanView($perms['view']);
                    $access->setCanCreate($perms['create']);
                    $access->setCanEdit($perms['edit']);
                    $access->setCanDelete($perms['delete']);
                    $this->em->persist($access);
                    $io->writeln(sprintf('  ✓ <info>Créé</info> %s → %s', $route, $role));
                }

                $this->em->flush();
            }
        }

        $io->success('Accès aux menus mis à jour.');

        return Command::SUCCESS;
    }
}
