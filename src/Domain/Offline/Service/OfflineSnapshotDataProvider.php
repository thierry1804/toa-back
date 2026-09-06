<?php

declare(strict_types=1);

namespace App\Domain\Offline\Service;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use App\Domain\ActivityPlanning\Entity\ActivityPlanning;
use App\Domain\Entreprise\Entity\Entreprise;
use App\Domain\Intervention\Entity\Intervention;
use App\Domain\Offline\Repository\TombstoneRecordRepository;
use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\Referentiel\Repository\CategorieRisqueRepository;
use App\Domain\Referentiel\Repository\InstallationEquipementRepository;
use App\Domain\Referentiel\Repository\SiteRepository;
use App\Domain\User\Entity\User;
use App\Doctrine\Extension\ActivityPlanningExtension;
use App\Doctrine\Extension\CurrentUserExtension;
use App\Doctrine\Extension\EntrepriseCollectionExtension;
use App\Doctrine\Extension\InterventionExtension;
use App\Doctrine\Extension\PlanPreventionExtension;
use App\Doctrine\Extension\UserCollectionExtension;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Point d'entrée unique pour le snapshot hors-ligne : détermine les modules
 * accessibles à l'utilisateur courant et fournit, pour chacun, exactement le
 * même périmètre (ownership + rôle) que le GET collection existant — en
 * réutilisant directement les QueryCollectionExtension déjà utilisées par ces
 * GET (PlanPreventionExtension, CurrentUserExtension, InterventionExtension,
 * ActivityPlanningExtension), plutôt qu'en réimplémentant la logique.
 */
class OfflineSnapshotDataProvider
{
    /** @var array<string, class-string> */
    private const MODULE_CLASS = [
        'plansPrevention'   => PlanPrevention::class,
        'permitsTravail'    => PermitTravail::class,
        'interventions'     => Intervention::class,
        'activityPlannings' => ActivityPlanning::class,
        'users'             => User::class,
        'entreprises'       => Entreprise::class,
    ];

    /** @var array<string, string> attribut de voter passé à Security::isGranted(), identique au GetCollection de la ressource */
    private const MODULE_VOTER_ATTRIBUTE = [
        'plansPrevention'   => 'PLAN_PREVENTION_VIEW',
        'permitsTravail'    => 'PERMIT_TRAVAIL_VIEW',
        'interventions'     => 'INTERVENTION_VIEW',
        'activityPlannings' => 'ACTIVITY_PLANNING_VIEW',
        'users'             => 'USER_VIEW',
        'entreprises'       => 'ENTREPRISE_VIEW',
    ];

    /** @var array<string, string> */
    private const MODULE_NORMALIZATION_GROUP = [
        'plansPrevention'   => 'plan_prevention:read',
        'permitsTravail'    => 'permit_travail:read',
        'interventions'     => 'intervention:read',
        'activityPlannings' => 'activity_planning:read',
        'users'             => 'user:read',
        'entreprises'       => 'entreprise:read',
    ];

    /** @var array<string, string> */
    private const MODULE_TOMBSTONE_TYPE = [
        'plansPrevention'   => 'plan_prevention',
        'permitsTravail'    => 'permit_travail',
        'interventions'     => 'intervention',
        'activityPlannings' => 'activity_planning',
        'users'             => 'user',
        'entreprises'       => 'entreprise',
    ];

    public const ENTITY_MODULES = [
        'plansPrevention', 'permitsTravail', 'interventions', 'activityPlannings', 'users', 'entreprises',
    ];

    public const ALWAYS_ON_MODULES = ['sites', 'referentiel'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly PlanPreventionExtension $planPreventionExtension,
        private readonly CurrentUserExtension $permitTravailExtension,
        private readonly InterventionExtension $interventionExtension,
        private readonly ActivityPlanningExtension $activityPlanningExtension,
        private readonly UserCollectionExtension $userExtension,
        private readonly EntrepriseCollectionExtension $entrepriseExtension,
        private readonly TombstoneRecordRepository $tombstoneRepository,
        private readonly CategorieRisqueRepository $categorieRisqueRepository,
        private readonly InstallationEquipementRepository $installationEquipementRepository,
        private readonly SiteRepository $siteRepository,
    ) {}

    /**
     * @return list<string> modules "entité" (hors sites/référentiel, toujours accessibles
     *                       à tout utilisateur authentifié) visibles pour l'utilisateur courant.
     */
    public function accessibleEntityModules(): array
    {
        return array_values(array_filter(
            self::ENTITY_MODULES,
            fn (string $module): bool => $this->isEntityModuleAccessible($module),
        ));
    }

    public function isEntityModuleAccessible(string $module): bool
    {
        if (!isset(self::MODULE_VOTER_ATTRIBUTE[$module])) {
            return false;
        }

        // Les voters de ce projet lèvent AccessDeniedException directement sur refus
        // (au lieu de retourner false) — pensé pour #[IsGranted]/denyAccessUnlessGranted().
        // Ici on veut un simple "cet utilisateur a-t-il accès ?" module par module, donc
        // on intercepte : un refus ne doit jamais faire échouer toute la requête snapshot,
        // seulement omettre ce module.
        try {
            return $this->security->isGranted(self::MODULE_VOTER_ATTRIBUTE[$module]);
        } catch (AccessDeniedException) {
            return false;
        }
    }

    public function normalizationGroup(string $module): string
    {
        return self::MODULE_NORMALIZATION_GROUP[$module]
            ?? throw new \InvalidArgumentException(sprintf('Unknown offline snapshot module "%s".', $module));
    }

    /**
     * QueryBuilder scopé exactement comme le GET collection de la ressource (mêmes
     * extensions Doctrine), avec filtre optionnel `updatedAt >= since`.
     */
    public function scopedQueryBuilder(string $module, ?\DateTimeImmutable $since): QueryBuilder
    {
        $class = self::MODULE_CLASS[$module]
            ?? throw new \InvalidArgumentException(sprintf('Unknown offline snapshot entity module "%s".', $module));

        $queryBuilder = $this->entityManager->createQueryBuilder()->select('e')->from($class, 'e');
        $nameGenerator = new QueryNameGenerator();

        match ($module) {
            'plansPrevention'   => $this->planPreventionExtension->applyToCollection($queryBuilder, $nameGenerator, $class),
            'permitsTravail'    => $this->permitTravailExtension->applyToCollection($queryBuilder, $nameGenerator, $class),
            'interventions'     => $this->interventionExtension->applyToCollection($queryBuilder, $nameGenerator, $class),
            'activityPlannings' => $this->activityPlanningExtension->applyToCollection($queryBuilder, $nameGenerator, $class),
            'users'             => $this->userExtension->applyToCollection($queryBuilder, $nameGenerator, $class),
            'entreprises'       => $this->entrepriseExtension->applyToCollection($queryBuilder, $nameGenerator, $class),
        };

        if ($since !== null) {
            $queryBuilder->andWhere('e.updatedAt >= :since')->setParameter('since', $since);
        }

        return $queryBuilder;
    }

    /**
     * @return list<string> ids des entités de ce module supprimées depuis $since.
     */
    public function tombstoneIds(string $module, \DateTimeImmutable $since): array
    {
        $type = self::MODULE_TOMBSTONE_TYPE[$module] ?? null;
        if ($type === null) {
            return [];
        }

        return $this->tombstoneRepository->findDeletedIdsSince($type, $since);
    }

    /**
     * Sites : pas d'ownership (identique à ReferentielSiteListController), accessible
     * à tout utilisateur authentifié (aligné sur ReferentielSiteSearchController, pas
     * sur le menu admin "Gestion de sites").
     */
    public function sitesQueryBuilder(?\DateTimeImmutable $since): QueryBuilder
    {
        $queryBuilder = $this->siteRepository->createQueryBuilder('e');
        if ($since !== null) {
            $queryBuilder->andWhere('e.updatedAt >= :since')->setParameter('since', $since);
        }

        return $queryBuilder;
    }

    /**
     * @return list<array{id:string,nom:string,typePermis:?string,parentId:?string,parentNom:?string,updatedAt:?\DateTimeImmutable}>
     */
    public function categoriesRisque(): array
    {
        $rows = $this->categorieRisqueRepository->createQueryBuilder('c')
            ->leftJoin('c.parent', 'p')
            ->select('c.id', 'c.nom', 'c.typePermis', 'c.updatedAt', 'IDENTITY(c.parent) AS parentId', 'p.nom AS parentNom')
            ->orderBy('c.nom', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => [
            'id'         => (string) $row['id'],
            'nom'        => $row['nom'],
            'typePermis' => $row['typePermis'],
            'parentId'   => $row['parentId'] !== null ? (string) $row['parentId'] : null,
            'parentNom'  => $row['parentNom'],
            'updatedAt'  => $row['updatedAt'],
        ], $rows);
    }

    /**
     * @return list<array{id:string,nom:string}>
     */
    public function installationsEquipements(): array
    {
        $rows = $this->installationEquipementRepository->createQueryBuilder('ie')
            ->select('ie.id', 'ie.nom')
            ->where('ie.deletedAt IS NULL')
            ->orderBy('ie.nom', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => [
            'id'  => (string) $row['id'],
            'nom' => $row['nom'],
        ], $rows);
    }

    /**
     * GeoJSON de tous les sites — même requête que ReferentielSiteGeoJsonController,
     * sans filtre d'ownership (données de référence, comme le module `sites`).
     *
     * @return array{type:string,features:list<array<string,mixed>>}
     */
    public function sitesGeoJson(): array
    {
        $rows = $this->siteRepository->createQueryBuilder('s')
            ->select(
                's.id',
                's.codeSite',
                's.nomSite',
                's.longitude',
                's.latitude',
                's.altitude',
                's.couleurMarqueur',
                's.fokontany',
                's.commune',
                's.district',
            )
            ->getQuery()
            ->getArrayResult();

        $features = [];
        foreach ($rows as $s) {
            $features[] = [
                'type'     => 'Feature',
                'geometry' => [
                    'type'        => 'Point',
                    'coordinates' => [$s['longitude'], $s['latitude']],
                ],
                'properties' => [
                    'id'  => (string) $s['id'],
                    'cs'  => $s['codeSite'],
                    'n'   => $s['nomSite'],
                    'a'   => $s['altitude'],
                    'c'   => $s['couleurMarqueur'],
                    'fok' => $s['fokontany'],
                    'com' => $s['commune'],
                    'dis' => $s['district'],
                ],
            ];
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }
}
