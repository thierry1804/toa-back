<?php

declare(strict_types=1);

namespace App\Domain\Referentiel\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Validation et parsing d'un fichier KMZ de sites, partagés entre l'import réel
 * (ReferentielSiteKmzImportController) et son aperçu (ReferentielSiteKmzPreviewController).
 */
final class KmzSiteFileParser
{
    private const MAX_FILE_SIZE = 20 * 1024 * 1024;
    private const ALLOWED_MIMES = [
        'application/vnd.google-earth.kmz',
        'application/zip',
    ];

    public function validateFile(UploadedFile $file): void
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
     * @return array{placemarks: list<array{name: string, codeSite: string|null, latitude: float, longitude: float, altitude: float|null, description: string|null, couleurMarqueur: string|null, fokontany: string|null, commune: string|null, district: string|null, typeSite: string|null, zone: string|null, typePylone: string|null, hauteurPylone: float|null, apn: bool|null, api: bool|null}>, ignored: list<array{name: string, reason: string}>}
     */
    public function parse(string $path): array
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
     * @return array{placemarks: list<array{name: string, codeSite: string|null, latitude: float, longitude: float, altitude: float|null, description: string|null, couleurMarqueur: string|null, fokontany: string|null, commune: string|null, district: string|null, typeSite: string|null, zone: string|null, typePylone: string|null, hauteurPylone: float|null, apn: bool|null, api: bool|null}>, ignored: list<array{name: string, reason: string}>}
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

        $results = [];
        $ignored = [];

        foreach ($placemarks as $placemark) {
            $name        = (string) ($placemark->name ?? 'Site sans nom');
            $coordinates = null;

            if (isset($placemark->Point->coordinates)) {
                $coordinates = trim((string) $placemark->Point->coordinates);
            } elseif (isset($placemark->MultiGeometry->Point->coordinates)) {
                $coordinates = trim((string) $placemark->MultiGeometry->Point->coordinates);
            }

            if ($coordinates === null || $coordinates === '') {
                $ignored[] = ['name' => $name, 'reason' => 'coordinates_missing'];
                continue;
            }

            $parts = explode(',', $coordinates);
            if (count($parts) < 2) {
                $ignored[] = ['name' => $name, 'reason' => 'coordinates_invalid'];
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
                'apn'             => $extendedData['apn'],
                'api'             => $extendedData['api'],
            ];
        }

        return ['placemarks' => $results, 'ignored' => $ignored];
    }

    /** @return array{codeSite: string|null, fokontany: string|null, commune: string|null, district: string|null, typeSite: string|null, zone: string|null, typePylone: string|null, hauteurPylone: float|null, apn: bool|null, api: bool|null} */
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
            'apn'           => null,
            'api'           => null,
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
                'apn'                                 => $result['apn']           = KmzFlagParser::parse($val),
                'api'                                 => $result['api']           = KmzFlagParser::parse($val),
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
