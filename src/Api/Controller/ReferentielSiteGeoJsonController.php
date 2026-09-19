<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\Referentiel\Repository\SiteRepository;
use App\Security\Voter\ReferentielSiteVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/referentiel/sites/geojson', name: 'referentiel_site_geojson', methods: ['GET'])]
#[IsGranted(ReferentielSiteVoter::VIEW)]
class ReferentielSiteGeoJsonController extends AbstractController
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
    ) {
    }

    public function __invoke(): JsonResponse
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
                's.apn',
                's.api',
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
                    'id'  => $s['id'],
                    'cs'  => $s['codeSite'],
                    'n'   => $s['nomSite'],
                    'a'   => $s['altitude'],
                    'c'   => $s['couleurMarqueur'],
                    'fok' => $s['fokontany'],
                    'com' => $s['commune'],
                    'dis' => $s['district'],
                    'apn' => (bool) ($s['apn'] ?? false),
                    'api' => (bool) ($s['api'] ?? false),
                ],
            ];
        }

        return $this->json([
            'type'     => 'FeatureCollection',
            'features' => $features,
        ]);
    }
}
