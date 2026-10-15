<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\Referentiel\Entity\ImportKmzSite;
use App\Domain\Referentiel\Entity\Site;
use App\Domain\Referentiel\Repository\SiteRepository;
use App\Domain\Referentiel\Service\KmzSiteFileParser;
use App\Security\Voter\ReferentielSiteVoter;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/referentiel/sites/import-kmz', name: 'referentiel_site_import_kmz', methods: ['POST'])]
#[IsGranted(ReferentielSiteVoter::CREATE)]
class ReferentielSiteKmzImportController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SiteRepository $siteRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        if ($file === null) {
            throw new UnprocessableEntityHttpException('kmz_file_required');
        }

        $parser = new KmzSiteFileParser();
        $parser->validateFile($file);

        ['placemarks' => $placemarks, 'ignored' => $ignored] = $parser->parse($file->getPathname());

        $import = new ImportKmzSite();
        $import->setOriginalFilename($file->getClientOriginalName());
        $import->setNomFichier($file->getClientOriginalName());
        $this->entityManager->persist($import);

        $lookup = $this->siteRepository->buildLookupMap();

        $sites = [];
        foreach ($placemarks as $placemark) {
            $key      = $placemark['name']
                . '|' . ($placemark['commune'] ?? '')
                . '|' . ($placemark['district'] ?? '');
            $existing = $lookup[$key] ?? null;

            if ($existing !== null) {
                if ($placemark['codeSite'] !== null) {
                    $existing->setCodeSite($placemark['codeSite']);
                }
                $existing->setLatitude($placemark['latitude']);
                $existing->setLongitude($placemark['longitude']);
                $existing->setAltitude($placemark['altitude']);
                $existing->setCouleurMarqueur($placemark['couleurMarqueur']);
                $existing->setTypeSite($placemark['typeSite']);
                $existing->setZone($placemark['zone']);
                $existing->setTypePylone($placemark['typePylone']);
                $existing->setHauteurPylone($placemark['hauteurPylone']);
                // Colonne absente ou vide (null) : on conserve le statut APN/API déjà connu du site.
                if ($placemark['apn'] !== null) {
                    $existing->setApn($placemark['apn']);
                }
                if ($placemark['api'] !== null) {
                    $existing->setApi($placemark['api']);
                }
                $existing->setImportKmz($import);
                $existing->setUpdatedAt(new \DateTime());
                $sites[] = $existing;
            } else {
                $site = new Site();
                $site->setNomSite($placemark['name']);
                $site->setCodeSite($placemark['codeSite'] ?? $placemark['name']);
                $site->setLatitude($placemark['latitude']);
                $site->setLongitude($placemark['longitude']);
                $site->setAltitude($placemark['altitude']);
                $site->setDescription($placemark['description']);
                $site->setCouleurMarqueur($placemark['couleurMarqueur']);
                $site->setFokontany($placemark['fokontany']);
                $site->setCommune($placemark['commune']);
                $site->setDistrict($placemark['district']);
                $site->setTypeSite($placemark['typeSite']);
                $site->setZone($placemark['zone']);
                $site->setTypePylone($placemark['typePylone']);
                $site->setHauteurPylone($placemark['hauteurPylone']);
                $site->setApn($placemark['apn'] ?? false);
                $site->setApi($placemark['api'] ?? false);
                $site->setSourceKmz(true);
                $site->setImportKmz($import);
                $this->entityManager->persist($site);
                $sites[]      = $site;
                $lookup[$key] = $site;
            }
        }

        $import->setNombreSites(count($sites));
        $this->entityManager->flush();

        $this->logger->info('referentiel_kmz_import_completed', [
            'importId'  => (string) $import->getId(),
            'nbSites'   => count($sites),
            'nbIgnores' => count($ignored),
        ]);

        return $this->json([
            'importId'     => (string) $import->getId(),
            'nomFichier'   => $import->getNomFichier(),
            'nombreSites'  => count($sites),
            'sitesIgnores' => count($ignored),
            'ignored'      => $ignored,
            'sites'        => array_map(static fn (Site $s): array => [
                'id'              => (string) $s->getId(),
                'codeSite'        => $s->getCodeSite(),
                'nomSite'         => $s->getNomSite(),
                'latitude'        => $s->getLatitude(),
                'longitude'       => $s->getLongitude(),
                'altitude'        => $s->getAltitude(),
                'fokontany'       => $s->getFokontany(),
                'commune'         => $s->getCommune(),
                'district'        => $s->getDistrict(),
                'couleurMarqueur' => $s->getCouleurMarqueur(),
                'typeSite'        => $s->getTypeSite(),
                'zone'            => $s->getZone(),
                'typePylone'      => $s->getTypePylone(),
                'hauteurPylone'   => $s->getHauteurPylone(),
                'apn'             => $s->isApn(),
                'api'             => $s->isApi(),
            ], $sites),
        ]);
    }
}
