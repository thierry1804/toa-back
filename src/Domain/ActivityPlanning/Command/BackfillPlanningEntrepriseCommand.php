<?php

declare(strict_types=1);

namespace App\Domain\ActivityPlanning\Command;

use App\Domain\ActivityPlanning\Entity\ActivityPlanning;
use App\Domain\ActivityPlanning\Service\PlanningEntrepriseResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * R-04 : rattrapage des plannings créés avant que l'entreprise du prestataire
 * ne soit renseignée automatiquement (champ resté vide sur tout l'historique).
 */
#[AsCommand(
    name: 'app:planning:backfill-entreprise',
    description: "Renseigne l'entreprise des plannings sans entreprise à partir du prestataire désigné",
)]
class BackfillPlanningEntrepriseCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PlanningEntrepriseResolver $resolver,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche les rattachements sans les enregistrer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        /** @var ActivityPlanning[] $plannings */
        $plannings = $this->entityManager->getRepository(ActivityPlanning::class)->findBy(['entreprise' => null]);

        $rows       = [];
        $unresolved = 0;
        foreach ($plannings as $planning) {
            $entreprise = $this->resolver->resolve($planning);
            if ($entreprise === null) {
                $unresolved++;
            } elseif (!$dryRun) {
                $planning->setEntreprise($entreprise);
            }
            $rows[] = [$planning->getId(), $planning->getProvider(), $planning->getProviderEmail(), $entreprise?->getNom() ?? '— non trouvée —'];
        }

        $io->table(['Planning', 'Prestataire', 'Email', 'Entreprise'], $rows);

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $io->success(sprintf(
            '%d planning(s) sans entreprise, %d rattaché(s)%s, %d non résolu(s).',
            count($plannings),
            count($plannings) - $unresolved,
            $dryRun ? ' (simulation)' : '',
            $unresolved,
        ));

        return Command::SUCCESS;
    }
}
