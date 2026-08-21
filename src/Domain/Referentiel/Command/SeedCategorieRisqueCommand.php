<?php

declare(strict_types=1);

namespace App\Domain\Referentiel\Command;

use App\Domain\Referentiel\Entity\CategorieRisque;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:categorie-risque:seed',
    description: 'Insère la hiérarchie catégories/sous-catégories de risque du référentiel (idempotent)',
)]
class SeedCategorieRisqueCommand extends Command
{
    private const HIERARCHY = [
        'Risques liés à l\'environnement' => [
            'Pollutions (déversement)',
            'Incendie',
            'Changement climatique',
        ],
        'Risque Social' => [
            'Contestation riveraine',
            'Sureté',
            'Autre(s) à préciser',
        ],
        'Risque lié à la santé et sécurité' => [
            'Accident lié à la sécurité routière',
            'Risque chimique',
            'Risque en hauteur',
            'Risque d\'ensevelissement et/ou effondrement',
            'Risque de noyade',
            'Risques liés aux installations électrique',
            'Risque lié à la manipulation des outils à la main',
            'Risque lié à la manipulation des outillages électroportatifs',
            'Accident lié à manutention mécanique',
            'Accident lié à manutention manuelle',
            'Risque lié au travail à chaud',
            'Risque lié au travail isolé',
            'Risque lié aux coactivités',
            'Risque lié à l\'ambiance thermique',
            'Risque lié au bruit',
            'Risques psychosociaux',
            'Risque face aux maladies infectieuses',
        ],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $repository = $this->entityManager->getRepository(CategorieRisque::class);

        $created = 0;

        foreach (self::HIERARCHY as $rootNom => $children) {
            $root = $repository->findOneBy(['nom' => $rootNom]);
            if ($root === null) {
                $root = new CategorieRisque();
                $root->setNom($rootNom);
                $this->entityManager->persist($root);
                $created++;
                $io->writeln(sprintf('  + Catégorie : %s', $rootNom));
            }

            foreach ($children as $childNom) {
                $child = $repository->findOneBy(['nom' => $childNom]);
                if ($child === null) {
                    $child = new CategorieRisque();
                    $child->setNom($childNom);
                    $child->setParent($root);
                    $this->entityManager->persist($child);
                    $created++;
                    $io->writeln(sprintf('    - Sous-catégorie : %s', $childNom));
                } elseif ($child->getParent() === null) {
                    // Existing flat record from before the hierarchy was introduced — attach it.
                    $child->setParent($root);
                    $io->writeln(sprintf('    ~ Rattachement : %s → %s', $childNom, $rootNom));
                }
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf('%d catégorie(s)/sous-catégorie(s) créée(s).', $created));

        return Command::SUCCESS;
    }
}
