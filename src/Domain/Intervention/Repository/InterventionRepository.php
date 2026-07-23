<?php

declare(strict_types=1);

namespace App\Domain\Intervention\Repository;

use App\Domain\Intervention\Entity\Intervention;
use App\Domain\Intervention\Enum\StatutIntervention;
use App\Domain\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Intervention>
 */
class InterventionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Intervention::class);
    }

    /**
     * Returns scalar rows: id, statut, permitReference, codeSite,
     * avancementMoyen (AVG), nbSuivis (COUNT), dernierSuivi (MAX date).
     *
     * Role filtering:
     *  - isHse=true  → all interventions
     *  - isHse=false → only interventions whose planPrevention.chefProjet = currentUser
     */
    public function getDashboardData(
        User $currentUser,
        bool $isHse,
        ?string $codeSite,
        ?\DateTimeImmutable $dateDebut,
        ?\DateTimeImmutable $dateFin,
        ?string $statut,
    ): array {
        $qb = $this->createQueryBuilder('i')
            ->select(
                'i.id as id',
                'i.statut as statut',
                'pt.reference as permitReference',
                'pt.codeSite as codeSite',
                'COALESCE(AVG(sj.avancementPourcentage), 0) as avancementMoyen',
                'COUNT(DISTINCT sj.id) as nbSuivis',
                'MAX(sj.date) as dernierSuivi',
            )
            ->leftJoin('i.permitTravail', 'pt')
            ->leftJoin('pt.planPrevention', 'pp')
            ->leftJoin('i.suivis', 'sj')
            ->groupBy('i.id, i.statut, pt.reference, pt.codeSite');

        if (!$isHse) {
            $qb->andWhere('pp.chefProjet = :currentUser')
               ->setParameter('currentUser', $currentUser);
        }

        if ($codeSite !== null && $codeSite !== '') {
            $qb->andWhere('pt.codeSite = :codeSite')
               ->setParameter('codeSite', $codeSite);
        }
        if ($dateDebut !== null) {
            $qb->andWhere('i.createdAt >= :dateDebut')
               ->setParameter('dateDebut', $dateDebut);
        }
        if ($dateFin !== null) {
            $qb->andWhere('i.createdAt <= :dateFin')
               ->setParameter('dateFin', $dateFin);
        }
        if ($statut !== null && $statut !== '') {
            $statutEnum = StatutIntervention::tryFrom($statut);
            if ($statutEnum !== null) {
                $qb->andWhere('i.statut = :statut')
                   ->setParameter('statut', $statutEnum->value);
            }
        }

        return $qb->getQuery()->getScalarResult();
    }

    /**
     * Loads a single Intervention with all relations needed for the HSE detail view.
     */
    public function findForHseDetail(string $id): ?Intervention
    {
        return $this->createQueryBuilder('i')
            ->select('i', 'pt', 'pp', 'sj', 'sjd', 'ev')
            ->leftJoin('i.permitTravail', 'pt')
            ->leftJoin('pt.planPrevention', 'pp')
            ->leftJoin('i.suivis', 'sj')
            ->leftJoin('sj.documents', 'sjd')
            ->leftJoin('i.evaluations', 'ev')
            ->where('i.id = :id')
            ->setParameter('id', $id)
            ->addOrderBy('sj.date', 'DESC')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Active interventions filtered by site for stats-journalieres endpoint.
     * Returns scalar rows: id, statut, permitReference, codeSite, avancementMoyen, nbSuivis.
     */
    public function getActiveInterventionsBySite(
        ?string $codeSite,
        User $currentUser,
        bool $isHse,
    ): array {
        $qb = $this->createQueryBuilder('i')
            ->select(
                'i.id as id',
                'i.statut as statut',
                'pt.reference as permitReference',
                'pt.codeSite as codeSite',
                'COALESCE(AVG(sj.avancementPourcentage), 0) as avancementMoyen',
                'COUNT(DISTINCT sj.id) as nbSuivis',
            )
            ->leftJoin('i.permitTravail', 'pt')
            ->leftJoin('pt.planPrevention', 'pp')
            ->leftJoin('i.suivis', 'sj')
            ->andWhere('i.statut IN (:actifs)')
            ->setParameter('actifs', [
                StatutIntervention::EN_COURS->value,
                StatutIntervention::EN_PREPARATION->value,
                StatutIntervention::EVALUATION_COMPLETE->value,
            ])
            ->groupBy('i.id, i.statut, pt.reference, pt.codeSite');

        if (!$isHse) {
            $qb->andWhere('pp.chefProjet = :currentUser')
               ->setParameter('currentUser', $currentUser);
        }

        if ($codeSite !== null && $codeSite !== '') {
            $qb->andWhere('pt.codeSite = :codeSite')
               ->setParameter('codeSite', $codeSite);
        }

        return $qb->getQuery()->getScalarResult();
    }

    /**
     * Count suivis created on a specific date, optionally filtered by site.
     */
    public function countSuivisForDate(
        \DateTimeImmutable $date,
        ?string $codeSite,
        User $currentUser,
        bool $isHse,
    ): int {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(sj.id)')
            ->from(\App\Domain\Intervention\Entity\SuiviJournalier::class, 'sj')
            ->leftJoin('sj.intervention', 'i')
            ->leftJoin('i.permitTravail', 'pt')
            ->leftJoin('pt.planPrevention', 'pp')
            ->andWhere('sj.date = :date')
            ->setParameter('date', $date->format('Y-m-d'));

        if (!$isHse) {
            $qb->andWhere('pp.chefProjet = :currentUser')
               ->setParameter('currentUser', $currentUser);
        }

        if ($codeSite !== null && $codeSite !== '') {
            $qb->andWhere('pt.codeSite = :codeSite')
               ->setParameter('codeSite', $codeSite);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
