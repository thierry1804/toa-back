<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Entity\SitePrevention;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class KmzImportProcessor implements ProcessorInterface
{
    private const MAX_FILE_SIZE = 20 * 1024 * 1024;
    private const ALLOWED_MIMES = [
        'application/vnd.google-earth.kmz',
        'application/zip',
    ];

    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            throw new BadRequestHttpException('no_request');
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        if ($file === null) {
            throw new UnprocessableEntityHttpException('kmz_file_required');
        }

        $this->validateFile($file);

        $plan = $data instanceof PlanPrevention
            ? $data
            : $this->entityManager->find(PlanPrevention::class, $uriVariables['id'] ?? null);

        if (!$plan instanceof PlanPrevention) {
            throw new UnprocessableEntityHttpException('plan_prevention.not_found');
        }

        ['placemarks' => $placemarks, 'nbIgnores' => $nbIgnores] = $this->parseKmz($file->getPathname());

        $sites = [];
        foreach ($placemarks as $placemark) {
            $site = new SitePrevention();
            $site->setPlanPrevention($plan);
            $site->setNom($placemark['name']);
            $site->setLatitude($placemark['latitude']);
            $site->setLongitude($placemark['longitude']);
            $site->setAltitude($placemark['altitude']);
            $site->setDescription($placemark['description']);
            $site->setCouleurMarqueur($placemark['couleurMarqueur']);
            $site->setOrdreAffichage($placemark['ordreAffichage']);
            $site->setSourceKmz(true);

            $this->entityManager->persist($site);
            $sites[] = $site;
        }

        $this->entityManager->flush();

        $sitesData = array_map(static function (SitePrevention $site): array {
            return [
                'id'              => (string) $site->getId(),
                'nom'             => $site->getNom(),
                'latitude'        => $site->getLatitude(),
                'longitude'       => $site->getLongitude(),
                'altitude'        => $site->getAltitude(),
                'description'     => $site->getDescription(),
                'couleurMarqueur' => $site->getCouleurMarqueur(),
                'ordreAffichage'  => $site->getOrdreAffichage(),
            ];
        }, $sites);

        $userId  = $this->tokenStorage->getToken()?->getUser()?->getUserIdentifier() ?? 'anonymous';
        $planId  = (string) $plan->getId();
        $nbSites = count($placemarks);

        $this->logger->info('kmz_import_completed', [
            'planId'    => $planId,
            'nbSites'   => $nbSites,
            'nbIgnores' => $nbIgnores,
            'userId'    => $userId,
        ]);

        return new JsonResponse([
            'sitesImportes' => $nbSites,
            'sitesIgnores'  => $nbIgnores,
            'sites'         => $sitesData,
        ]);
    }

    private function validateFile(UploadedFile $file): void
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext !== 'kmz') {
            throw new UnprocessableEntityHttpException('Format KMZ requis');
        }

        if (!in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            throw new UnprocessableEntityHttpException('Format KMZ requis');
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new UnprocessableEntityHttpException('Format KMZ requis');
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
     * @return array{placemarks: array<int, array{name: string, latitude: float, longitude: float, altitude: float|null, description: string|null, couleurMarqueur: string|null, ordreAffichage: int}>, nbIgnores: int}
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
     * @return array{placemarks: array<int, array{name: string, latitude: float, longitude: float, altitude: float|null, description: string|null, couleurMarqueur: string|null, ordreAffichage: int}>, nbIgnores: int}
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
        $ordre     = 1;

        foreach ($placemarks as $placemark) {
            $name        = (string) ($placemark->name ?? 'Site sans nom');
            $coordinates = null;

            if (isset($placemark->Point->coordinates)) {
                $coordinates = trim((string) $placemark->Point->coordinates);
            } elseif (isset($placemark->MultiGeometry->Point->coordinates)) {
                $coordinates = trim((string) $placemark->MultiGeometry->Point->coordinates);
            }

            if ($coordinates === null || $coordinates === '') {
                $this->logger->warning('kmz_placemark_ignored_no_coordinates', ['name' => $name]);
                ++$nbIgnores;
                continue;
            }

            $parts = explode(',', $coordinates);
            if (count($parts) < 2) {
                $this->logger->warning('kmz_placemark_ignored_invalid_coordinates', ['name' => $name, 'raw' => $coordinates]);
                ++$nbIgnores;
                continue;
            }

            $description = isset($placemark->description) ? trim((string) $placemark->description) : null;
            $description = ($description === '') ? null : $description;

            $results[] = [
                'name'            => $name,
                'longitude'       => (float) $parts[0],
                'latitude'        => (float) $parts[1],
                'altitude'        => isset($parts[2]) ? (float) $parts[2] : null,
                'description'     => $description,
                'couleurMarqueur' => $this->extractColor($placemark, $styleMap),
                'ordreAffichage'  => $ordre++,
            ];
        }

        return ['placemarks' => $results, 'nbIgnores' => $nbIgnores];
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
            $color = null;
            if (isset($style->IconStyle->color)) {
                $color = $this->kmlColorToHex((string) $style->IconStyle->color);
            }
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
            $styleUrl = trim((string) $placemark->styleUrl);

            return $styleMap[$styleUrl] ?? null;
        }

        return null;
    }

    private function kmlColorToHex(string $kmlColor): ?string
    {
        $kmlColor = trim($kmlColor);
        if (strlen($kmlColor) !== 8) {
            return null;
        }
        // KML format AABBGGRR → #RRGGBB
        $r = substr($kmlColor, 6, 2);
        $g = substr($kmlColor, 4, 2);
        $b = substr($kmlColor, 2, 2);

        return '#' . strtoupper($r . $g . $b);
    }
}
