<?php

declare(strict_types=1);

namespace App\Domain\Referentiel\Repository;

use App\Domain\Referentiel\Entity\ImportKmzSite;
use App\Domain\Referentiel\Entity\Site;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Site>
 */
class SiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Site::class);
    }

    public function findByImportKmz(ImportKmzSite $import): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.importKmz = :import')
            ->setParameter('import', $import)
            ->getQuery()
            ->getResult();
    }

    public function findByNomCommuneDistrict(string $nom, ?string $commune, ?string $district): ?Site
    {
        $qb = $this->createQueryBuilder('s')
            ->where('s.nomSite = :nom')
            ->setParameter('nom', $nom);

        if ($commune !== null) {
            $qb->andWhere('s.commune = :commune')->setParameter('commune', $commune);
        } else {
            $qb->andWhere('s.commune IS NULL');
        }

        if ($district !== null) {
            $qb->andWhere('s.district = :district')->setParameter('district', $district);
        } else {
            $qb->andWhere('s.district IS NULL');
        }

        return $qb->setMaxResults(1)->getQuery()->getOneOrNullResult();
    }

    /**
     * @return list<array{codeSite: string, nomSite: string}>
     */
    public function search(string $q, int $limit = 10): array
    {
        $escaped = addcslashes($q, '%_');

        return $this->createQueryBuilder('s')
            ->select('s.codeSite', 's.nomSite')
            ->where('s.codeSite LIKE :q OR s.nomSite LIKE :q')
            ->setParameter('q', '%' . $escaped . '%')
            ->orderBy('s.codeSite', 'ASC')
            ->setMaxResults(min($limit, 20))
            ->getQuery()
            ->getArrayResult();
    }

    public function findByCodeSite(string $code): ?Site
    {
        return $this->createQueryBuilder('s')
            ->where('LOWER(s.codeSite) = LOWER(:code)')
            ->setParameter('code', $code)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Charge tous les sites en 1 requête et retourne un tableau indexé par "nomSite|commune|district".
     * Utilisé pour l'import KMZ bulk afin d'éviter le N+1.
     *
     * @return array<string, Site>
     */
    public function buildLookupMap(): array
    {
        /** @var Site[] $all */
        $all = $this->createQueryBuilder('s')->getQuery()->getResult();

        $map = [];
        foreach ($all as $site) {
            $key = $site->getNomSite()
                . '|' . ($site->getCommune() ?? '')
                . '|' . ($site->getDistrict() ?? '');
            $map[$key] = $site;
        }

        return $map;
    }
}
