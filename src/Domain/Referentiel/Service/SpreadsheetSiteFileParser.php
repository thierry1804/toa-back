<?php

declare(strict_types=1);

namespace App\Domain\Referentiel\Service;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Validation et parsing d'un fichier CSV/XLSX de sites, partagés entre l'import réel
 * (ReferentielSiteSpreadsheetImportController) et son aperçu (ReferentielSiteSpreadsheetPreviewController).
 */
final class SpreadsheetSiteFileParser
{
    private const MAX_FILE_SIZE = 20 * 1024 * 1024;
    private const REQUIRED_COLUMNS = ['codeSite', 'nomSite', 'latitude', 'longitude'];

    /** @var array<string, string> en-têtes reconnus (normalisés en minuscule, sans espace) → nom de champ Site */
    private const COLUMN_ALIASES = [
        'codesite'       => 'codeSite',
        'code_site'      => 'codeSite',
        'code'           => 'codeSite',
        'nomsite'        => 'nomSite',
        'nom_site'       => 'nomSite',
        'nom'            => 'nomSite',
        'latitude'       => 'latitude',
        'lat'            => 'latitude',
        'longitude'      => 'longitude',
        'long'           => 'longitude',
        'lon'            => 'longitude',
        'lng'            => 'longitude',
        'altitude'       => 'altitude',
        'alt'            => 'altitude',
        'description'    => 'description',
        'fokontany'      => 'fokontany',
        'commune'        => 'commune',
        'district'       => 'district',
        'typesite'       => 'typeSite',
        'type_site'      => 'typeSite',
        'type'           => 'typeSite',
        'zone'           => 'zone',
        'typepylone'     => 'typePylone',
        'type_pylone'    => 'typePylone',
        'pylone'         => 'typePylone',
        'hauteurpylone'  => 'hauteurPylone',
        'hauteur_pylone' => 'hauteurPylone',
        'hauteur'        => 'hauteurPylone',
        'apn'            => 'apn',
        'api'            => 'api',
        'couleurmarqueur' => 'couleurMarqueur',
        'couleur_marqueur' => 'couleurMarqueur',
        'couleur'        => 'couleurMarqueur',
    ];

    public function validateFile(UploadedFile $file): void
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['csv', 'xlsx'], true)) {
            throw new UnprocessableEntityHttpException('Format CSV ou XLSX requis');
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new UnprocessableEntityHttpException('Fichier trop volumineux (max 20 MB)');
        }
    }

    /**
     * @return array{rows: list<array{codeSite: string, nomSite: string, latitude: float, longitude: float, altitude: float|null, description: string|null, couleurMarqueur: string|null, fokontany: string|null, commune: string|null, district: string|null, typeSite: string|null, zone: string|null, typePylone: string|null, hauteurPylone: float|null, apn: bool|null, api: bool|null}>, errors: list<array{line: int, codeSite: string|null, nomSite: string|null, reason: string}>}
     */
    public function parse(UploadedFile $file): array
    {
        $ext    = strtolower($file->getClientOriginalExtension());
        $reader = $ext === 'xlsx' ? IOFactory::createReader('Xlsx') : $this->createCsvReader($file->getPathname());

        try {
            $spreadsheet = $reader->load($file->getPathname());
        } catch (\Throwable) {
            throw new UnprocessableEntityHttpException('spreadsheet_invalid_file');
        }

        $sheet = $spreadsheet->getActiveSheet();
        $data  = $sheet->toArray(null, true, true, false);

        if (count($data) === 0) {
            throw new UnprocessableEntityHttpException('spreadsheet_empty');
        }

        $columnMap = $this->mapHeaderRow(array_shift($data));

        $missing = array_diff(self::REQUIRED_COLUMNS, array_values($columnMap));
        if ($missing !== []) {
            throw new UnprocessableEntityHttpException('spreadsheet_missing_required_columns: ' . implode(', ', $missing));
        }

        $rows    = [];
        $errors  = [];
        $seenCodes = [];
        $lineNumber = 1; // la ligne 1 est l'en-tête

        foreach ($data as $cells) {
            ++$lineNumber;

            if ($this->isBlankRow($cells)) {
                continue;
            }

            $row = [];
            foreach ($columnMap as $columnIndex => $field) {
                $row[$field] = isset($cells[$columnIndex]) ? trim((string) $cells[$columnIndex]) : '';
            }

            $codeSite = $row['codeSite'] !== '' ? $row['codeSite'] : null;
            $nomSite  = $row['nomSite'] !== '' ? $row['nomSite'] : null;

            if ($codeSite === null) {
                $errors[] = ['line' => $lineNumber, 'codeSite' => null, 'nomSite' => $nomSite, 'reason' => 'code_site_required'];
                continue;
            }

            if ($nomSite === null) {
                $errors[] = ['line' => $lineNumber, 'codeSite' => $codeSite, 'nomSite' => null, 'reason' => 'nom_site_required'];
                continue;
            }

            $latitude = $this->parseFloat($row['latitude'] ?? '');
            if ($latitude === null) {
                $errors[] = ['line' => $lineNumber, 'codeSite' => $codeSite, 'nomSite' => $nomSite, 'reason' => 'latitude_invalid'];
                continue;
            }

            $longitude = $this->parseFloat($row['longitude'] ?? '');
            if ($longitude === null) {
                $errors[] = ['line' => $lineNumber, 'codeSite' => $codeSite, 'nomSite' => $nomSite, 'reason' => 'longitude_invalid'];
                continue;
            }

            $codeKey = strtolower($codeSite);
            if (isset($seenCodes[$codeKey])) {
                $errors[] = ['line' => $lineNumber, 'codeSite' => $codeSite, 'nomSite' => $nomSite, 'reason' => 'duplicate_code_site'];
                continue;
            }
            $seenCodes[$codeKey] = true;

            $rows[] = [
                'codeSite'        => $codeSite,
                'nomSite'         => $nomSite,
                'latitude'        => $latitude,
                'longitude'       => $longitude,
                'altitude'        => $this->parseFloat($row['altitude'] ?? ''),
                'description'     => ($row['description'] ?? '') !== '' ? $row['description'] : null,
                'couleurMarqueur' => ($row['couleurMarqueur'] ?? '') !== '' ? $row['couleurMarqueur'] : null,
                'fokontany'       => ($row['fokontany'] ?? '') !== '' ? $row['fokontany'] : null,
                'commune'         => ($row['commune'] ?? '') !== '' ? $row['commune'] : null,
                'district'        => ($row['district'] ?? '') !== '' ? $row['district'] : null,
                'typeSite'        => ($row['typeSite'] ?? '') !== '' ? $row['typeSite'] : null,
                'zone'            => ($row['zone'] ?? '') !== '' ? $row['zone'] : null,
                'typePylone'      => ($row['typePylone'] ?? '') !== '' ? $row['typePylone'] : null,
                'hauteurPylone'   => $this->parseFloat($row['hauteurPylone'] ?? ''),
                'apn'             => ($row['apn'] ?? '') !== '' ? KmzFlagParser::parse($row['apn']) : null,
                'api'             => ($row['api'] ?? '') !== '' ? KmzFlagParser::parse($row['api']) : null,
            ];
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    private function createCsvReader(string $path): Csv
    {
        $reader = new Csv();
        $reader->setDelimiter($this->sniffDelimiter($path));

        return $reader;
    }

    private function sniffDelimiter(string $path): string
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return ',';
        }
        $firstLine = (string) fgets($handle);
        fclose($handle);

        $commaCount     = substr_count($firstLine, ',');
        $semicolonCount = substr_count($firstLine, ';');

        return $semicolonCount > $commaCount ? ';' : ',';
    }

    /**
     * @param list<mixed> $headerRow
     * @return array<int, string> index de colonne (0-based) → nom de champ Site
     */
    private function mapHeaderRow(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $index => $cell) {
            $normalized = strtolower(str_replace([' ', '-'], '_', trim((string) $cell)));
            if (isset(self::COLUMN_ALIASES[$normalized])) {
                $map[$index] = self::COLUMN_ALIASES[$normalized];
            }
        }

        return $map;
    }

    /** @param list<mixed> $cells */
    private function isBlankRow(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function parseFloat(string $value): ?float
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $normalized = str_replace(',', '.', $value);
        if (!is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }
}
