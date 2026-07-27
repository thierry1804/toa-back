<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Repository;

use App\Domain\PermitTravail\Entity\KpiIntervention;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<KpiIntervention>
 */
class KpiInterventionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, KpiIntervention::class);
    }

    public function findBySiteAndPeriode(string $codeSite, string $periode): ?KpiIntervention
    {
        return $this->findOneBy(['codeSite' => $codeSite, 'periode' => $periode]);
    }

    public function findGlobalForPeriode(string $periode): ?KpiIntervention
    {
        $row = $this->createQueryBuilder('k')
            ->select(
                'SUM(k.nbPermisCloturesValides) AS valides',
                'SUM(k.nbPermisCloturesTotal)   AS total',
                'AVG(k.delaiMoyenValidationCdp) AS delai',
            )
            ->where('k.periode = :periode')
            ->setParameter('periode', $periode)
            ->getQuery()
            ->getSingleResult();

        $valides = (int) ($row['valides'] ?? 0);
        $total   = (int) ($row['total'] ?? 0);
        $delai   = round((float) ($row['delai'] ?? 0.0), 2);

        if ($total === 0 && $valides === 0) {
            return null;
        }

        $kpi = new KpiIntervention();
        $kpi->setCodeSite('*');
        $kpi->setPeriode($periode);
        $kpi->setNbPermisCloturesValides($valides);
        $kpi->setNbPermisCloturesTotal($total);
        $kpi->setTauxCloture($total > 0 ? round($valides / $total, 4) : 0.0);
        $kpi->setDelaiMoyenValidationCdp($delai);
        $kpi->setUpdatedAt(new \DateTimeImmutable());

        return $kpi;
    }

    /** @return KpiIntervention[] ordered ASC by periode */
    public function findEvolutionBySite(string $codeSite, int $nbMois): array
    {
        $periodes = $this->lastNMonths($nbMois);

        return $this->createQueryBuilder('k')
            ->where('k.codeSite = :site')
            ->andWhere('k.periode IN (:periodes)')
            ->setParameter('site', $codeSite)
            ->setParameter('periodes', $periodes)
            ->orderBy('k.periode', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return KpiIntervention[] — synthetic aggregated rows, one per month */
    public function findEvolutionGlobal(int $nbMois): array
    {
        $periodes = $this->lastNMonths($nbMois);

        $rows = $this->createQueryBuilder('k')
            ->select(
                'k.periode                         AS periode',
                'SUM(k.nbPermisCloturesValides)    AS valides',
                'SUM(k.nbPermisCloturesTotal)      AS total',
                'AVG(k.delaiMoyenValidationCdp)    AS delai',
                'AVG(k.tauxIncidents)              AS tauxIncidents',
                'AVG(k.tempsMoyenValidationPlan)   AS tmpPlan',
                'AVG(k.tempsMoyenValidationPermis) AS tmpPermis',
                'AVG(k.tempsMoyenValidationPv)     AS tmpPv',
                'AVG(k.avancementMoyen)            AS avancement',
            )
            ->where('k.periode IN (:periodes)')
            ->setParameter('periodes', $periodes)
            ->groupBy('k.periode')
            ->orderBy('k.periode', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(static function (array $row): KpiIntervention {
            $valides = (int) ($row['valides'] ?? 0);
            $total   = (int) ($row['total']   ?? 0);
            $kpi     = new KpiIntervention();
            $kpi->setCodeSite('*');
            $kpi->setPeriode((string) $row['periode']);
            $kpi->setNbPermisCloturesValides($valides);
            $kpi->setNbPermisCloturesTotal($total);
            $kpi->setTauxCloture($total > 0 ? round($valides / $total, 4) : 0.0);
            $kpi->setDelaiMoyenValidationCdp(round((float) ($row['delai'] ?? 0.0), 2));
            $kpi->setTauxIncidents(isset($row['tauxIncidents'])  ? round((float) $row['tauxIncidents'],  2) : null);
            $kpi->setTempsMoyenValidationPlan(isset($row['tmpPlan'])   ? round((float) $row['tmpPlan'],   2) : null);
            $kpi->setTempsMoyenValidationPermis(isset($row['tmpPermis']) ? round((float) $row['tmpPermis'], 2) : null);
            $kpi->setTempsMoyenValidationPv(isset($row['tmpPv'])     ? round((float) $row['tmpPv'],     2) : null);
            $kpi->setAvancementMoyen(isset($row['avancement'])  ? round((float) $row['avancement'],  2) : null);
            $kpi->setUpdatedAt(new \DateTimeImmutable());

            return $kpi;
        }, $rows);
    }

    /** @return string[] — e.g. ['2025-10', '2025-11', ..., '2026-04'] */
    private function lastNMonths(int $n): array
    {
        $periodes = [];
        $now      = new \DateTimeImmutable('first day of this month');
        for ($i = $n - 1; $i >= 0; --$i) {
            $periodes[] = $now->modify("-{$i} month")->format('Y-m');
        }

        return $periodes;
    }
}
