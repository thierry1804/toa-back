<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Service;

use App\Domain\PermitTravail\Entity\KpiIntervention;
use App\Domain\PermitTravail\Repository\KpiInterventionRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class DashboardKpisService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly KpiInterventionRepository $kpiRepository,
    ) {}

    private function conn(): Connection
    {
        return $this->em->getConnection();
    }

    /**
     * Builds WHERE fragments + params for a date column.
     * Returns [conditions[], params[]]
     */
    private function dateFilter(
        string $alias,
        string $col,
        ?string $periode,
        ?\DateTimeImmutable $dateDebut,
        ?\DateTimeImmutable $dateFin,
    ): array {
        $conds  = [];
        $params = [];

        if ($periode !== null && $periode !== '') {
            $conds[]          = "TO_CHAR({$alias}.{$col}, 'YYYY-MM') = :periode";
            $params['periode'] = $periode;
        } else {
            if ($dateDebut !== null) {
                $conds[]             = "{$alias}.{$col} >= :dateDebut";
                $params['dateDebut'] = $dateDebut->format('Y-m-d');
            }
            if ($dateFin !== null) {
                $conds[]           = "{$alias}.{$col} <= :dateFin";
                $params['dateFin'] = $dateFin->format('Y-m-d 23:59:59');
            }
        }

        return [$conds, $params];
    }

    private function siteFilter(string $alias, ?string $codeSite): array
    {
        if ($codeSite === null || $codeSite === '') {
            return [[], []];
        }

        return [
            ["{$alias}.code_site = :codeSite"],
            ['codeSite' => $codeSite],
        ];
    }

    private function buildWhere(array ...$filters): array
    {
        $conds  = [];
        $params = [];
        foreach ($filters as [$c, $p]) {
            array_push($conds, ...$c);
            $params += $p;
        }
        $sql = $conds !== [] ? ('WHERE ' . implode(' AND ', $conds)) : '';

        return [$sql, $params];
    }

    public function computeGlobal(
        ?string $codeSite,
        ?string $periode,
        ?\DateTimeImmutable $dateDebut,
        ?\DateTimeImmutable $dateFin,
    ): array {
        $conn = $this->conn();

        // --- nbPermisValides (HSE validated) ---
        [$dconds, $dparams] = $this->dateFilter('d', 'decided_at', $periode, $dateDebut, $dateFin);
        [$sconds, $sparams] = $this->siteFilter('pt', $codeSite);
        [$where, $params]   = $this->buildWhere(
            [$dconds, $dparams],
            [$sconds, $sparams],
            [["d.decision = 'VALIDE'"], []],
        );
        $nbPermisValides = (int) $conn->executeQuery(
            "SELECT COUNT(d.id)
             FROM decision_hse_permit_travail d
             JOIN permit_travail pt ON pt.id = d.permit_travail_id
             {$where}",
            $params,
        )->fetchOne();

        // --- nbPermisTotal (tous statuts) ---
        [$dconds, $dparams] = $this->dateFilter('pt', 'created_at', $periode, $dateDebut, $dateFin);
        [$sconds, $sparams] = $this->siteFilter('pt', $codeSite);
        [$where, $params]   = $this->buildWhere([$dconds, $dparams], [$sconds, $sparams]);
        $nbPermisTotal = (int) $conn->executeQuery(
            "SELECT COUNT(pt.id) FROM permit_travail pt {$where}",
            $params,
        )->fetchOne();

        // --- nbPermisClotures ---
        [$dconds, $dparams] = $this->dateFilter('cp', 'date_cloture_effective', $periode, $dateDebut, $dateFin);
        [$sconds, $sparams] = $this->siteFilter('pt', $codeSite);
        [$where, $params]   = $this->buildWhere([$dconds, $dparams], [$sconds, $sparams]);
        $nbPermisClotures = (int) $conn->executeQuery(
            "SELECT COUNT(cp.id)
             FROM cloture_permit cp
             JOIN permit_travail pt ON pt.id = cp.permit_travail_id
             {$where}",
            $params,
        )->fetchOne();

        // --- tauxCloture ---
        $tauxCloture = 0.0;
        if ($nbPermisClotures > 0) {
            [$dconds, $dparams] = $this->dateFilter('d', 'decided_at', $periode, $dateDebut, $dateFin);
            [$sconds, $sparams] = $this->siteFilter('pt', $codeSite);
            [$where, $params]   = $this->buildWhere(
                [$dconds, $dparams],
                [$sconds, $sparams],
                [["d.decision = 'VALIDE'"], []],
            );
            $nbPvValides = (int) $conn->executeQuery(
                "SELECT COUNT(d.id)
                 FROM decision_cdp_pv_reception d
                 JOIN permit_travail pt ON pt.id = d.permit_travail_id
                 {$where}",
                $params,
            )->fetchOne();
            $tauxCloture = round($nbPvValides / $nbPermisClotures, 4);
        }

        // --- nbPlansValides ---
        [$dconds, $dparams] = $this->dateFilter('d', 'decided_at', $periode, $dateDebut, $dateFin);
        [$sconds, $sparams] = $this->siteFilter('pp', $codeSite);
        [$where, $params]   = $this->buildWhere(
            [$dconds, $dparams],
            [$sconds, $sparams],
            [["d.decision = 'VALIDE'"], []],
        );
        $nbPlansValides = (int) $conn->executeQuery(
            "SELECT COUNT(d.id)
             FROM decision_hse_plan_prevention d
             JOIN plan_prevention pp ON pp.id = d.plan_prevention_id
             {$where}",
            $params,
        )->fetchOne();

        // --- tauxIncidents ---
        [$dconds, $dparams] = $this->dateFilter('cp', 'date_cloture_effective', $periode, $dateDebut, $dateFin);
        [$sconds, $sparams] = $this->siteFilter('pt', $codeSite);
        [$where, $params]   = $this->buildWhere([$dconds, $dparams], [$sconds, $sparams]);
        $risques = $conn->executeQuery(
            "SELECT
                COUNT(CASE WHEN er.niveau_risque >= 15 THEN 1 END) AS nb_eleves,
                COUNT(er.id)                                        AS nb_total
             FROM evaluation_risque er
             JOIN intervention i    ON i.id  = er.intervention_id
             JOIN permit_travail pt ON pt.id = i.permit_travail_id
             JOIN cloture_permit cp ON cp.permit_travail_id = pt.id
             {$where}",
            $params,
        )->fetchAssociative();
        $nbEleves      = (int) ($risques['nb_eleves'] ?? 0);
        $nbTotalRisques = (int) ($risques['nb_total']  ?? 0);
        $tauxIncidents  = $nbTotalRisques > 0 ? round($nbEleves / $nbTotalRisques * 100, 2) : 0.0;

        // --- tempsMoyenValidationPlan ---
        [$dconds, $dparams] = $this->dateFilter('d', 'decided_at', $periode, $dateDebut, $dateFin);
        [$sconds, $sparams] = $this->siteFilter('pp', $codeSite);
        [$where, $params]   = $this->buildWhere(
            [$dconds, $dparams],
            [$sconds, $sparams],
            [["d.decision = 'VALIDE'"], []],
        );
        $rawPlan = $conn->executeQuery(
            "SELECT AVG(EXTRACT(EPOCH FROM (d.decided_at - pp.created_at)) / 86400)
             FROM decision_hse_plan_prevention d
             JOIN plan_prevention pp ON pp.id = d.plan_prevention_id
             {$where}",
            $params,
        )->fetchOne();
        $tempsMoyenValidationPlan = round((float) ($rawPlan ?? 0.0), 2);

        // --- tempsMoyenValidationPermis ---
        [$dconds, $dparams] = $this->dateFilter('d', 'decided_at', $periode, $dateDebut, $dateFin);
        [$sconds, $sparams] = $this->siteFilter('pt', $codeSite);
        [$where, $params]   = $this->buildWhere(
            [$dconds, $dparams],
            [$sconds, $sparams],
            [["d.decision = 'VALIDE'"], []],
        );
        $rawPermis = $conn->executeQuery(
            "SELECT AVG(EXTRACT(EPOCH FROM (d.decided_at - pt.created_at)) / 86400)
             FROM decision_hse_permit_travail d
             JOIN permit_travail pt ON pt.id = d.permit_travail_id
             {$where}",
            $params,
        )->fetchOne();
        $tempsMoyenValidationPermis = round((float) ($rawPermis ?? 0.0), 2);

        // --- avancementMoyen ---
        [$dconds, $dparams] = $this->dateFilter('sj', 'date', $periode, $dateDebut, $dateFin);
        [$sconds, $sparams] = $this->siteFilter('pt', $codeSite);
        [$where, $params]   = $this->buildWhere([$dconds, $dparams], [$sconds, $sparams]);
        $rawAv = $conn->executeQuery(
            "SELECT AVG(sj.avancement_pourcentage)
             FROM suivi_journalier sj
             JOIN intervention i    ON i.id  = sj.intervention_id
             JOIN permit_travail pt ON pt.id = i.permit_travail_id
             {$where}",
            $params,
        )->fetchOne();
        $avancementMoyen = round((float) ($rawAv ?? 0.0), 2);

        // --- parSite (from KpiIntervention) ---
        $effectivePeriode = $periode ?? (new \DateTimeImmutable())->format('Y-m');
        $kpiRows          = $this->kpiRepository->findBy(['periode' => $effectivePeriode]);
        $parSite          = array_map(
            static fn (KpiIntervention $k) => [
                'codeSite' => $k->getCodeSite(),
                'kpis'     => self::kpiToArray($k),
            ],
            $kpiRows,
        );

        // --- evolution (last 6 months from KpiIntervention) ---
        $evolutionSite = $codeSite ?? '';
        $evolution     = $evolutionSite !== ''
            ? $this->kpiRepository->findEvolutionBySite($evolutionSite, 6)
            : [];
        $evolutionData = array_map(
            static fn (KpiIntervention $k) => [
                'periode' => $k->getPeriode(),
                'kpis'    => self::kpiToArray($k),
            ],
            $evolution,
        );

        return [
            'tauxIncidents'              => $tauxIncidents,
            'tempsMoyenValidationPlan'   => $tempsMoyenValidationPlan,
            'tempsMoyenValidationPermis' => $tempsMoyenValidationPermis,
            'avancementMoyen'            => $avancementMoyen,
            'nbPermisValides'            => $nbPermisValides,
            'nbPermisTotal'              => $nbPermisTotal,
            'nbPermisClotures'           => $nbPermisClotures,
            'nbPlansValides'             => $nbPlansValides,
            'tauxCloture'                => $tauxCloture,
            'parSite'                    => $parSite,
            'evolution'                  => $evolutionData,
        ];
    }

    public function computeSites(
        ?string $periode,
        ?\DateTimeImmutable $dateDebut,
        ?\DateTimeImmutable $dateFin,
    ): array {
        $effectivePeriode = $periode ?? (new \DateTimeImmutable())->format('Y-m');
        $kpiRows          = $this->kpiRepository->findBy(
            ['periode' => $effectivePeriode],
            ['nbPermisCloturesValides' => 'DESC'],
        );

        return array_map(static fn (KpiIntervention $k) => [
            'codeSite'   => $k->getCodeSite(),
            'periode'    => $k->getPeriode(),
            'classement' => 0,
            'kpis'       => self::kpiToArray($k),
        ], $kpiRows);
    }

    public function computeEvolution(?string $codeSite, int $nbMois): array
    {
        if ($codeSite !== null && $codeSite !== '') {
            $rows = $this->kpiRepository->findEvolutionBySite($codeSite, $nbMois);
        } else {
            $rows = $this->kpiRepository->findEvolutionGlobal($nbMois);
        }

        return array_map(static fn (KpiIntervention $k) => [
            'periode'  => $k->getPeriode(),
            'codeSite' => $k->getCodeSite(),
            'kpis'     => self::kpiToArray($k),
        ], $rows);
    }

    private static function kpiToArray(KpiIntervention $k): array
    {
        return [
            'nbPermisCloturesValides'    => $k->getNbPermisCloturesValides(),
            'nbPermisCloturesTotal'      => $k->getNbPermisCloturesTotal(),
            'tauxCloture'                => $k->getTauxCloture(),
            'delaiMoyenValidationCdp'    => $k->getDelaiMoyenValidationCdp(),
            'tauxIncidents'              => $k->getTauxIncidents(),
            'tempsMoyenValidationPlan'   => $k->getTempsMoyenValidationPlan(),
            'tempsMoyenValidationPermis' => $k->getTempsMoyenValidationPermis(),
            'avancementMoyen'            => $k->getAvancementMoyen(),
        ];
    }
}
