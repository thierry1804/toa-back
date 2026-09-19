<?php

declare(strict_types=1);

namespace App\Domain\PlanPrevention\Service;

use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Entity\SitePrevention;
use App\Domain\Referentiel\Entity\Site;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;

class ApnApiSiteResolver
{
    /** @var \WeakMap<PlanPrevention, null> plans chargés dont les sites APN/API ne sont pas encore résolus */
    private \WeakMap $pending;

    /** @var \WeakMap<PlanPrevention, list<array{codeSite: string, nomSite: string, apn: bool, api: bool}>> */
    private \WeakMap $resolved;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        $this->pending = new \WeakMap();
        $this->resolved = new \WeakMap();
    }

    /**
     * Déclare un plan chargé : sa résolution sera groupée avec celle des autres plans
     * en attente (2 requêtes au total, quel que soit le nombre de plans d'une liste).
     */
    public function register(PlanPrevention $plan): void
    {
        $this->pending[$plan] = null;
    }

    /**
     * Sites du plan situés en aire protégée (APN ou API), déduits des sites importés
     * dans le plan et des sites du référentiel désignés par leur code.
     *
     * @return list<array{codeSite: string, nomSite: string, apn: bool, api: bool}>
     */
    public function resolveForPlan(PlanPrevention $plan): array
    {
        if (!isset($this->resolved[$plan])) {
            $batch = [];
            foreach ($this->pending as $pendingPlan => $_) {
                $batch[] = $pendingPlan;
            }
            if (!in_array($plan, $batch, true)) {
                $batch[] = $plan;
            }
            $this->resolveBatch($batch);
        }

        $sites = $this->resolved[$plan];
        unset($this->resolved[$plan], $this->pending[$plan]);

        return $sites;
    }

    /** @param list<PlanPrevention> $plans */
    private function resolveBatch(array $plans): void
    {
        $planIds = [];
        $codesByPlan = [];
        $allCodes = [];
        foreach ($plans as $plan) {
            if ($plan->getId() !== null) {
                $planIds[] = $plan->getId()->toRfc4122();
            }
            $codesByPlan[spl_object_id($plan)] = $this->collectCodes($plan);
            array_push($allCodes, ...$codesByPlan[spl_object_id($plan)]);
        }

        /** @var array<string, array<string, array{codeSite: string, nomSite: string, apn: bool, api: bool}>> $importedByPlan */
        $importedByPlan = [];
        if ($planIds !== []) {
            /** @var list<array{planId: mixed, nom: string, apn: bool, api: bool, codeSite: string|null}> $rows */
            $rows = $this->entityManager->createQueryBuilder()
                ->select('IDENTITY(sp.planPrevention) AS planId', 'sp.nom AS nom', 'sp.apn AS apn', 'sp.api AS api', 's.codeSite AS codeSite')
                ->from(SitePrevention::class, 'sp')
                ->leftJoin('sp.site', 's')
                ->where('sp.planPrevention IN (:plans)')
                ->andWhere('(sp.apn = true OR sp.api = true)')
                ->setParameter('plans', $planIds, ArrayParameterType::STRING)
                ->getQuery()
                ->getArrayResult();

            foreach ($rows as $row) {
                $code = (string) ($row['codeSite'] ?? '');
                $importedByPlan[(string) $row['planId']][$code !== '' ? $code : 'sp:' . $row['nom']] = [
                    'codeSite' => $code,
                    'nomSite' => (string) $row['nom'],
                    'apn' => (bool) $row['apn'],
                    'api' => (bool) $row['api'],
                ];
            }
        }

        /** @var array<string, Site> $sitesByLowerCode */
        $sitesByLowerCode = [];
        $allCodes = array_values(array_unique($allCodes));
        if ($allCodes !== []) {
            /** @var Site[] $sites */
            $sites = $this->entityManager->createQueryBuilder()
                ->select('s')
                ->from(Site::class, 's')
                ->where('LOWER(s.codeSite) IN (:codes)')
                ->andWhere('(s.apn = true OR s.api = true)')
                ->setParameter('codes', array_map('mb_strtolower', $allCodes), ArrayParameterType::STRING)
                ->getQuery()
                ->getResult();

            foreach ($sites as $site) {
                $sitesByLowerCode[mb_strtolower($site->getCodeSite())] = $site;
            }
        }

        foreach ($plans as $plan) {
            $found = $importedByPlan[(string) $plan->getId()?->toRfc4122()] ?? [];

            foreach ($codesByPlan[spl_object_id($plan)] as $code) {
                $site = $sitesByLowerCode[mb_strtolower($code)] ?? null;
                if ($site === null) {
                    continue;
                }
                $found[$site->getCodeSite()] = [
                    'codeSite' => $site->getCodeSite(),
                    'nomSite' => $site->getNomSite(),
                    'apn' => $site->isApn(),
                    'api' => $site->isApi(),
                ];
            }

            $this->resolved[$plan] = array_values($found);
        }
    }

    /** @return list<string> */
    private function collectCodes(PlanPrevention $plan): array
    {
        $codes = [];

        foreach (preg_split('/[;,]/', (string) $plan->getCodeSite()) ?: [] as $code) {
            $code = trim($code);
            if ($code !== '') {
                $codes[] = $code;
            }
        }

        foreach ($plan->getPlanificationSites() as $site) {
            $code = is_array($site) ? trim((string) ($site['codeSite'] ?? '')) : '';
            if ($code !== '') {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }
}
