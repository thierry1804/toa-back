<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\Referentiel\Entity\ImportKmzSite;
use App\Domain\Referentiel\Entity\Site;
use App\Domain\Referentiel\Repository\SiteRepository;
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
    private const MAX_FILE_SIZE = 20 * 1024 * 1024;
    private const ALLOWED_MIMES = [
        'application/vnd.google-earth.kmz',
        'application/zip',
    ];

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

        $this->validateFile($file);

        ['placemarks' => $placemarks, 'nbIgnores' => $nbIgnores] = $this->parseKmz($file->getPathname());

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
            'nbIgnores' => $nbIgnores,
        ]);

        return $this->json([
            'importId'     => (string) $import->getId(),
            'nomFichier'   => $import->getNomFichier(),
            'nombreSites'  => count($sites),
            'sitesIgnores' => $nbIgnores,
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
            ], $sites),
        ]);
    }

    private function validateFile(UploadedFile $file): void
    {
        if (strtolower($file->getClientOriginalExtension()) !== 'kmz') {
            throw new UnprocessableEntityHttpException('Format KMZ requis');
        }

        if (!in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            throw new UnprocessableEntityHttpException('Format KMZ requis');
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new UnprocessableEntityHttpException('Fichier trop volumineux (max 20 MB)');
        }

        $zip = new \ZipArchive();
        if ($zip->open($file->getPathname()) !== true) {
            throw new UnprocessableEntityHttpException('Format KMZ requis');
        }

        $hasRootKml = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name !== false && !str_contains($name, '/') && str_ends_with(strtolower($name), '.kml')) {
                $hasRootKml = true;
                break;
            }
        }
        $zip->close();

        if (!$hasRootKml) {
            throw new UnprocessableEntityHttpException('Format KMZ requis');
        }
    }

    /**
     * @return array{placemarks: list<array{name: string, latitude: float, longitude: float, altitude: float|null, description: string|null, couleurMarqueur: string|null, fokontany: string|null, commune: string|null, district: string|null}>, nbIgnores: int}
     */
    private function parseKmz(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new UnprocessableEntityHttpException('kmz_invalid_zip');
        }

        $kmlContent = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name !== false && str_ends_with(strtolower($name), '.kml')) {
                $kmlContent = $zip->getFromIndex($i);
                break;
            }
        }
        $zip->close();

        if ($kmlContent === null || $kmlContent === false) {
            throw new UnprocessableEntityHttpException('kmz_no_kml_found');
        }

        return $this->parsePlacemarks($kmlContent);
    }

    /**
     * @return array{placemarks: list<array{name: string, codeSite: string|null, latitude: float, longitude: float, altitude: float|null, description: string|null, couleurMarqueur: string|null, fokontany: string|null, commune: string|null, district: string|null}>, nbIgnores: int}
     */
    private function parsePlacemarks(string $kmlContent): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($kmlContent);
        libxml_clear_errors();

        if ($xml === false) {
            throw new UnprocessableEntityHttpException('kmz_invalid_kml');
        }

        $xml->registerXPathNamespace('kml', 'http://www.opengis.net/kml/2.2');

        $styleMap   = $this->buildStyleMap($xml);
        $placemarks = $xml->xpath('//kml:Placemark') ?: $xml->xpath('//Placemark') ?: [];

        $results   = [];
        $nbIgnores = 0;

        foreach ($placemarks as $placemark) {
            $name        = (string) ($placemark->name ?? 'Site sans nom');
            $coordinates = null;

            if (isset($placemark->Point->coordinates)) {
                $coordinates = trim((string) $placemark->Point->coordinates);
            } elseif (isset($placemark->MultiGeometry->Point->coordinates)) {
                $coordinates = trim((string) $placemark->MultiGeometry->Point->coordinates);
            }

            if ($coordinates === null || $coordinates === '') {
                $this->logger->warning('referentiel_kmz_placemark_no_coordinates', ['name' => $name]);
                ++$nbIgnores;
                continue;
            }

            $parts = explode(',', $coordinates);
            if (count($parts) < 2) {
                ++$nbIgnores;
                continue;
            }

            $description  = isset($placemark->description) ? trim((string) $placemark->description) : null;
            $description  = ($description === '') ? null : $description;
            $extendedData = $this->extractExtendedData($placemark);

            $results[] = [
                'name'            => $name,
                'codeSite'        => $extendedData['codeSite'],
                'longitude'       => (float) $parts[0],
                'latitude'        => (float) $parts[1],
                'altitude'        => isset($parts[2]) ? (float) $parts[2] : null,
                'description'     => $description,
                'couleurMarqueur' => $this->extractColor($placemark, $styleMap),
                'fokontany'       => $extendedData['fokontany'],
                'commune'         => $extendedData['commune'],
                'district'        => $extendedData['district'],
                'typeSite'        => $extendedData['typeSite'],
                'zone'            => $extendedData['zone'],
                'typePylone'      => $extendedData['typePylone'],
                'hauteurPylone'   => $extendedData['hauteurPylone'],
            ];
        }

        return ['placemarks' => $results, 'nbIgnores' => $nbIgnores];
    }

    /** @return array{codeSite: string|null, fokontany: string|null, commune: string|null, district: string|null, typeSite: string|null, zone: string|null, typePylone: string|null, hauteurPylone: float|null} */
    private function extractExtendedData(\SimpleXMLElement $placemark): array
    {
        $result = [
            'codeSite'      => null,
            'fokontany'     => null,
            'commune'       => null,
            'district'      => null,
            'typeSite'      => null,
            'zone'          => null,
            'typePylone'    => null,
            'hauteurPylone' => null,
        ];

        if (!isset($placemark->ExtendedData)) {
            return $result;
        }

        foreach ($placemark->ExtendedData->Data as $data) {
            $attrs = $data->attributes();
            if ($attrs === null) {
                continue;
            }
            $key = strtolower(str_replace([' ', '-'], '_', (string) ($attrs['name'] ?? '')));
            $val = isset($data->value) ? trim((string) $data->value) : '';
            if ($val === '') {
                continue;
            }
            match ($key) {
                'code_site', 'codesite', 'code'      => $result['codeSite']      = $val,
                'fokontany'                           => $result['fokontany']     = $val,
                'commune'                             => $result['commune']       = $val,
                'district'                            => $result['district']      = $val,
                'type_site', 'typesite', 'type'       => $result['typeSite']      = $val,
                'zone'                                => $result['zone']          = $val,
                'type_pylone', 'typepylone', 'pylone' => $result['typePylone']    = $val,
                'hauteur_pylone', 'hauteurpylone',
                'hauteur'                             => $result['hauteurPylone'] = (float) preg_replace('/[^0-9.]/', '', $val) ?: null,
                default                               => null,
            };
        }

        return $result;
    }

    /** @return array<string, string|null> */
    private function buildStyleMap(\SimpleXMLElement $xml): array
    {
        $styleElements = $xml->xpath('//kml:Style[@id]') ?: $xml->xpath('//Style[@id]') ?: [];
        $map           = [];

        foreach ($styleElements as $style) {
            $attrs = $style->attributes();
            if ($attrs === null) {
                continue;
            }
            $id = (string) ($attrs['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $color = isset($style->IconStyle->color) ? $this->kmlColorToHex((string) $style->IconStyle->color) : null;
            $map['#' . $id] = $color;
        }

        return $map;
    }

    /** @param array<string, string|null> $styleMap */
    private function extractColor(\SimpleXMLElement $placemark, array $styleMap): ?string
    {
        if (isset($placemark->Style->IconStyle->color)) {
            return $this->kmlColorToHex((string) $placemark->Style->IconStyle->color);
        }

        if (isset($placemark->styleUrl)) {
            return $styleMap[trim((string) $placemark->styleUrl)] ?? null;
        }

        return null;
    }

    private function kmlColorToHex(string $kmlColor): ?string
    {
        $kmlColor = trim($kmlColor);
        if (strlen($kmlColor) !== 8) {
            return null;
        }
        $r = substr($kmlColor, 6, 2);
        $g = substr($kmlColor, 4, 2);
        $b = substr($kmlColor, 2, 2);

        return '#' . strtoupper($r . $g . $b);
    }
}
