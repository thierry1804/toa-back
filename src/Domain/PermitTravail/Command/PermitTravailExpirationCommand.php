<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Command;

use App\Domain\PermitTravail\Entity\CloturePerm;
use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Entity\PvReceptionPdf;
use App\Domain\PermitTravail\Enum\StatutPermitTravail;
use App\Domain\PermitTravail\Enum\StatutPvReceptionPdf;
use App\Domain\PermitTravail\Enum\TypeCloturePerm;
use App\Domain\PermitTravail\Message\GeneratePvReceptionMessage;
use App\Domain\PermitTravail\Repository\CloturePermRepository;
use App\Domain\PermitTravail\Repository\PvReceptionPdfRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:permit-travail:expire',
    description: 'Clôture automatiquement les permis de travail dont la date de fin prévue est dépassée',
)]
class PermitTravailExpirationCommand extends Command
{
    private const STATUTS_CLOTURABLES = [
        StatutPermitTravail::VALIDE_HSE,
        StatutPermitTravail::EN_COURS,
        StatutPermitTravail::SOUMIS,
        StatutPermitTravail::VALIDE,
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CloturePermRepository $clotureRepository,
        private readonly PvReceptionPdfRepository $pvRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io  = new SymfonyStyle($input, $output);
        $now = new \DateTimeImmutable();

        $io->section('Recherche des permis expirés');

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('p')
            ->from(PermitTravail::class, 'p')
            ->where('p.dateFinPrevue < :now')
            ->andWhere('p.statut IN (:statuts)')
            ->setParameter('now', $now)
            ->setParameter('statuts', array_map(fn($s) => $s->value, self::STATUTS_CLOTURABLES));

        /** @var PermitTravail[] $permits */
        $permits = $qb->getQuery()->getResult();

        if (empty($permits)) {
            $io->success('Aucun permis expiré à clôturer.');
            return Command::SUCCESS;
        }

        $io->note(sprintf('%d permis expiré(s) trouvé(s).', count($permits)));

        $processed = 0;

        foreach ($permits as $permit) {
            try {
                $existing = $this->clotureRepository->findByPermitTravailId((string) $permit->getId());
                if ($existing !== null) {
                    $io->note(sprintf('Permis %s déjà clôturé, ignoré.', $permit->getReference()));
                    continue;
                }

                $cloture = new CloturePerm();
                $cloture->setPermitTravail($permit);
                $cloture->setTypeCloture(TypeCloturePerm::AUTOMATIQUE);
                $cloture->setDateClotureEffective($now);
                $cloture->setCommentaire('Clôture automatique : date de fin prévue dépassée');
                $cloture->setAccordClient(false);

                $permit->setStatut(StatutPermitTravail::CLOTURE);

                $this->entityManager->persist($cloture);

                $pvExisting = $this->pvRepository->findByPermitTravailId((string) $permit->getId());
                $jobId      = Uuid::v4()->toRfc4122();

                if ($pvExisting !== null) {
                    $pvExisting->setStatut(StatutPvReceptionPdf::EN_COURS);
                    $pvExisting->setFilePath(null);
                    $pvExisting->setGenereAt(null);
                    $pvExisting->setTailleFichier(null);
                    $pvExisting->setJobId($jobId);
                } else {
                    $pvRecord = new PvReceptionPdf();
                    $pvRecord->setPermitTravail($permit);
                    $pvRecord->setGenerePar($permit->getCreatedBy());
                    $pvRecord->setJobId($jobId);
                    $pvRecord->setStatut(StatutPvReceptionPdf::EN_COURS);
                    $this->entityManager->persist($pvRecord);
                }

                $this->entityManager->flush();

                $this->messageBus->dispatch(new GeneratePvReceptionMessage(
                    permitTravailId: (string) $permit->getId(),
                    jobId: $jobId,
                    requesterId: $permit->getCreatedBy()?->getId() ?? 0,
                ));

                $io->writeln(sprintf('  ✓ Clôturé : <info>%s</info>', $permit->getReference()));
                $this->logger->info('Permit {ref} auto-clôturé (dateFinPrevue dépassée)', [
                    'ref'         => $permit->getReference(),
                    'permitId'    => (string) $permit->getId(),
                    'dateFinPrevue' => $permit->getDateFinPrevue()?->format(\DateTimeInterface::ATOM),
                ]);

                $processed++;
            } catch (\Throwable $e) {
                $io->error(sprintf('Erreur permis %s : %s', $permit->getReference(), $e->getMessage()));
                $this->logger->error('Erreur cloture automatique permit {ref}: {error}', [
                    'ref'   => $permit->getReference(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $io->success(sprintf('%d permis clôturé(s) avec succès.', $processed));

        return Command::SUCCESS;
    }
}
