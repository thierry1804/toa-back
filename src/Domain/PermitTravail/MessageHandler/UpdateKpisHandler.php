<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\MessageHandler;

use App\Domain\PermitTravail\Entity\KpiIntervention;
use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Message\UpdateKpisMessage;
use App\Domain\PermitTravail\Repository\KpiInterventionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class UpdateKpisHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly KpiInterventionRepository $kpiRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(UpdateKpisMessage $message): void
    {
        $permit = $this->em->find(PermitTravail::class, $message->permitTravailId);
        if (!$permit instanceof PermitTravail) {
            $this->logger->warning('[KPI] UpdateKpisHandler: permit not found', [
                'permitTravailId' => $message->permitTravailId,
            ]);

            return;
        }

        $codeSite  = (string) $permit->getCodeSite();
        $decidedAt = new \DateTimeImmutable($message->decidedAt);
        $periode   = $decidedAt->format('Y-m');

        $kpi = $this->kpiRepository->findBySiteAndPeriode($codeSite, $periode);
        if ($kpi === null) {
            $kpi = new KpiIntervention();
            $kpi->setCodeSite($codeSite);
            $kpi->setPeriode($periode);
        }

        $conn = $this->em->getConnection();

        $valides = (int) $conn->executeQuery(
            "SELECT COUNT(d.id)
               FROM decision_cdp_pv_reception d
               JOIN permit_travail p ON p.id = d.permit_travail_id
              WHERE p.code_site = :site
                AND d.decision = 'VALIDE'
                AND TO_CHAR(d.decided_at, 'YYYY-MM') = :periode",
            ['site' => $codeSite, 'periode' => $periode],
        )->fetchOne();

        $total = (int) $conn->executeQuery(
            "SELECT COUNT(cp.id)
               FROM cloture_permit cp
               JOIN permit_travail p ON p.id = cp.permit_travail_id
              WHERE p.code_site = :site
                AND TO_CHAR(cp.date_cloture_effective, 'YYYY-MM') = :periode",
            ['site' => $codeSite, 'periode' => $periode],
        )->fetchOne();

        $rawDelai = $conn->executeQuery(
            "SELECT AVG(EXTRACT(EPOCH FROM (d.decided_at - cp.date_cloture_effective)) / 86400)
               FROM decision_cdp_pv_reception d
               JOIN permit_travail p ON p.id = d.permit_travail_id
               JOIN cloture_permit cp ON cp.permit_travail_id = p.id
              WHERE p.code_site = :site
                AND d.decision = 'VALIDE'
                AND TO_CHAR(d.decided_at, 'YYYY-MM') = :periode",
            ['site' => $codeSite, 'periode' => $periode],
        )->fetchOne();

        $delai = round((float) ($rawDelai ?? 0.0), 2);

        // taux incidents : nb évaluations niveauRisque >= 15 / total * 100
        $risques = $conn->executeQuery(
            "SELECT
                COUNT(CASE WHEN er.niveau_risque >= 15 THEN 1 END) AS nb_eleves,
                COUNT(er.id)                                        AS nb_total
             FROM evaluation_risque er
             JOIN intervention i      ON i.id  = er.intervention_id
             JOIN permit_travail pt   ON pt.id = i.permit_travail_id
             JOIN cloture_permit cp   ON cp.permit_travail_id = pt.id
            WHERE pt.code_site = :site
              AND TO_CHAR(cp.date_cloture_effective, 'YYYY-MM') = :periode",
            ['site' => $codeSite, 'periode' => $periode],
        )->fetchAssociative();
        $nbEleves = (int) ($risques['nb_eleves'] ?? 0);
        $nbTotal  = (int) ($risques['nb_total']  ?? 0);
        $tauxIncidents = $nbTotal > 0 ? round($nbEleves / $nbTotal * 100, 2) : 0.0;

        // temps moyen validation plan de prévention (decidedAt - createdAt, en jours)
        $rawPlan = $conn->executeQuery(
            "SELECT AVG(EXTRACT(EPOCH FROM (d.decided_at - pp.created_at)) / 86400)
             FROM decision_hse_plan_prevention d
             JOIN plan_prevention pp ON pp.id = d.plan_prevention_id
            WHERE pp.code_site = :site
              AND d.decision = 'VALIDE'
              AND TO_CHAR(d.decided_at, 'YYYY-MM') = :periode",
            ['site' => $codeSite, 'periode' => $periode],
        )->fetchOne();
        $tempsMoyenPlan = round((float) ($rawPlan ?? 0.0), 2);

        // temps moyen validation permis HSE (decidedAt - permit_travail.created_at, en jours)
        $rawPermis = $conn->executeQuery(
            "SELECT AVG(EXTRACT(EPOCH FROM (d.decided_at - pt.created_at)) / 86400)
             FROM decision_hse_permit_travail d
             JOIN permit_travail pt ON pt.id = d.permit_travail_id
            WHERE pt.code_site = :site
              AND d.decision = 'VALIDE'
              AND TO_CHAR(d.decided_at, 'YYYY-MM') = :periode",
            ['site' => $codeSite, 'periode' => $periode],
        )->fetchOne();
        $tempsMoyenPermis = round((float) ($rawPermis ?? 0.0), 2);

        // avancement moyen suivi journalier (%)
        $rawAvancement = $conn->executeQuery(
            "SELECT AVG(sj.avancement_pourcentage)
             FROM suivi_journalier sj
             JOIN intervention i    ON i.id  = sj.intervention_id
             JOIN permit_travail pt ON pt.id = i.permit_travail_id
            WHERE pt.code_site = :site
              AND TO_CHAR(sj.date, 'YYYY-MM') = :periode",
            ['site' => $codeSite, 'periode' => $periode],
        )->fetchOne();
        $avancementMoyen = round((float) ($rawAvancement ?? 0.0), 2);

        $kpi->setNbPermisCloturesValides($valides);
        $kpi->setNbPermisCloturesTotal($total);
        $kpi->setTauxCloture($total > 0 ? round($valides / $total, 4) : 0.0);
        $kpi->setDelaiMoyenValidationCdp($delai);
        $kpi->setTauxIncidents($tauxIncidents);
        $kpi->setTempsMoyenValidationPlan($tempsMoyenPlan);
        $kpi->setTempsMoyenValidationPermis($tempsMoyenPermis);
        $kpi->setTempsMoyenValidationPv($delai);
        $kpi->setAvancementMoyen($avancementMoyen);
        $kpi->setUpdatedAt(new \DateTimeImmutable());

        $this->em->persist($kpi);
        $this->em->flush();

        $this->logger->info('[KPI] kpi_intervention updated', [
            'codeSite'                   => $codeSite,
            'periode'                    => $periode,
            'nbPermisCloturesValides'    => $valides,
            'nbPermisCloturesTotal'      => $total,
            'tauxCloture'                => $kpi->getTauxCloture(),
            'delaiMoyenValidationCdp'    => $delai,
            'tauxIncidents'              => $tauxIncidents,
            'tempsMoyenValidationPlan'   => $tempsMoyenPlan,
            'tempsMoyenValidationPermis' => $tempsMoyenPermis,
            'avancementMoyen'            => $avancementMoyen,
        ]);
    }
}
