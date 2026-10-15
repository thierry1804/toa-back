<?php

declare(strict_types=1);

namespace App\Domain\Intervention\Repository;

use App\Domain\Intervention\Entity\Intervention;
use App\Domain\Intervention\Enum\StatutIntervention;
use App\Domain\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
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
     * avancementMoyen (latest suivi avancementPourcentage by date), nbSuivis (COUNT), dernierSuivi (MAX date).
     *
     * Role filtering: see applyOwnershipFilter().
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
                'COUNT(DISTINCT sj.id) as nbSuivis',
                'MAX(sj.date) as dernierSuivi',
            )
            ->leftJoin('i.permitTravail', 'pt')
            ->leftJoin('pt.planPrevention', 'pp')
            ->leftJoin('i.suivis', 'sj')
            ->groupBy('i.id, i.statut, pt.reference, pt.codeSite');

        $this->applyOwnershipFilter($qb, $currentUser, $isHse);

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

        $rows = $qb->getQuery()->getScalarResult();

        $interventionIds = array_map(static fn (array $row): string => (string) $row['id'], $rows);
        $latestAvancements = $this->fetchLatestAvancementByIntervention($interventionIds);

        foreach ($rows as &$row) {
            $row['avancementMoyen'] = $latestAvancements[(string) $row['id']] ?? 0;
        }
        unset($row);

        return $rows;
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

        $this->applyOwnershipFilter($qb, $currentUser, $isHse);

        if ($codeSite !== null && $codeSite !== '') {
            $qb->andWhere('pt.codeSite = :codeSite')
               ->setParameter('codeSite', $codeSite);
        }

        $rows = $qb->getQuery()->getScalarResult();

        $interventionIds = array_map(static fn (array $row): string => (string) $row['id'], $rows);
        $latestAvancements = $this->fetchLatestAvancementByIntervention($interventionIds);

        foreach ($rows as &$row) {
            $row['avancementMoyen'] = $latestAvancements[(string) $row['id']] ?? 0;
        }
        unset($row);

        return $rows;
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

        $this->applyOwnershipFilter($qb, $currentUser, $isHse);

        if ($codeSite !== null && $codeSite !== '') {
            $qb->andWhere('pt.codeSite = :codeSite')
               ->setParameter('codeSite', $codeSite);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Role filtering shared by getDashboardData()/getActiveInterventionsBySite()/
     * countSuivisForDate(), all of which join i.permitTravail as 'pt' and
     * pt.planPrevention as 'pp' before calling this method.
     *
     *  - isHse=true                        -> no filter, all interventions visible.
     *  - isHse=false, currentUser has an entreprise (prestataire-side roles:
     *    ROLE_PRESTATAIRE, ROLE_CHEF_PROJET_PRESTATAIRE, ROLE_AGENT_TERRAIN,
     *    ROLE_HSE cote prestataire) -> scoped to every permit created by a
     *    member of that entreprise (team-wide), never pp.chefProjet, which is
     *    never a prestataire-side user.
     *  - isHse=false, currentUser has no entreprise (ROLE_CHEF_PROJET TOA)
     *    -> unchanged legacy behaviour: pp.chefProjet = currentUser.
     */
    private function applyOwnershipFilter(\Doctrine\ORM\QueryBuilder $qb, User $currentUser, bool $isHse): void
    {
        if ($isHse) {
            return;
        }

        $entreprise = $currentUser->getEntreprise();
        if ($entreprise !== null) {
            $qb->leftJoin('pt.createdBy', 'ptCreator')
               ->andWhere('ptCreator.entreprise = :entreprise')
               ->setParameter('entreprise', $entreprise);

            return;
        }

        // R-32 : chef de projet TOA rattaché à des processus -> dossiers de ses processus.
        if ($currentUser->getProcessus() !== []) {
            $qb->andWhere('pp.typeIntervention IN (:processus)')
               ->setParameter('processus', $currentUser->getProcessus());

            return;
        }

        $qb->andWhere('pp.chefProjet = :currentUser')
           ->setParameter('currentUser', $currentUser);
    }

    /**
     * Returns, per intervention id, the avancementPourcentage of its most recent
     * suivi journalier (by date, then by createdAt as a tie-breaker), using a
     * native DISTINCT ON query since this cannot be expressed as a correlated
     * value alongside a GROUP BY in DQL.
     *
     * @param string[] $interventionIds
     *
     * @return array<string, int> map of interventionId (string) to avancementPourcentage
     */
    private function fetchLatestAvancementByIntervention(array $interventionIds): array
    {
        if ($interventionIds === []) {
            return [];
        }

        $sql = 'SELECT DISTINCT ON (intervention_id) intervention_id, avancement_pourcentage '
            . 'FROM suivi_journalier '
            . 'ORDER BY intervention_id, date DESC, created_at DESC';

        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative($sql);

        $latestByIntervention = [];
        foreach ($rows as $row) {
            $latestByIntervention[$row['intervention_id']] = (int) $row['avancement_pourcentage'];
        }

        $wantedIds = array_flip($interventionIds);

        return array_intersect_key($latestByIntervention, $wantedIds);
    }
}
