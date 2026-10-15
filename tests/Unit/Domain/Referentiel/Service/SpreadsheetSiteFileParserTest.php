<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Referentiel\Service;

use App\Domain\Referentiel\Service\SpreadsheetSiteFileParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class SpreadsheetSiteFileParserTest extends TestCase
{
    /** @var list<string> */
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $file) {
            @unlink($file);
        }
    }

    public function testValidCsvProducesRows(): void
    {
        $csv = "codeSite,nomSite,latitude,longitude,commune,district\n"
            . "SITE-001,Ankazobe,-18.9,47.5,C1,D1\n"
            . "SITE-002,Ambohijafy,-18.8,47.4,C2,D2\n";

        $result = $this->parse($csv, 'csv');

        $this->assertCount(2, $result['rows']);
        $this->assertSame([], $result['errors']);
        $this->assertSame('SITE-001', $result['rows'][0]['codeSite']);
        $this->assertSame(-18.9, $result['rows'][0]['latitude']);
    }

    public function testSemicolonDelimiterIsDetected(): void
    {
        $csv = "codeSite;nomSite;latitude;longitude\nSITE-001;Ankazobe;-18.9;47.5\n";

        $result = $this->parse($csv, 'csv');

        $this->assertCount(1, $result['rows']);
    }

    public function testMissingRequiredColumnThrows(): void
    {
        $csv = "nomSite,latitude,longitude\nAnkazobe,-18.9,47.5\n";

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->parse($csv, 'csv');
    }

    public function testRowMissingCodeSiteIsAnError(): void
    {
        $csv = "codeSite,nomSite,latitude,longitude\n,Ankazobe,-18.9,47.5\n";

        $result = $this->parse($csv, 'csv');

        $this->assertSame([], $result['rows']);
        $this->assertCount(1, $result['errors']);
        $this->assertSame('code_site_required', $result['errors'][0]['reason']);
    }

    public function testInvalidLatitudeIsAnError(): void
    {
        $csv = "codeSite,nomSite,latitude,longitude\nSITE-001,Ankazobe,not-a-number,47.5\n";

        $result = $this->parse($csv, 'csv');

        $this->assertSame([], $result['rows']);
        $this->assertSame('latitude_invalid', $result['errors'][0]['reason']);
    }

    public function testDuplicateCodeSiteInFileIsAnError(): void
    {
        $csv = "codeSite,nomSite,latitude,longitude\n"
            . "SITE-001,Ankazobe,-18.9,47.5\n"
            . "SITE-001,Ankazobe bis,-18.8,47.4\n";

        $result = $this->parse($csv, 'csv');

        $this->assertCount(1, $result['rows']);
        $this->assertCount(1, $result['errors']);
        $this->assertSame('duplicate_code_site', $result['errors'][0]['reason']);
    }

    public function testBlankLinesAreSkipped(): void
    {
        $csv = "codeSite,nomSite,latitude,longitude\nSITE-001,Ankazobe,-18.9,47.5\n\n\n";

        $result = $this->parse($csv, 'csv');

        $this->assertCount(1, $result['rows']);
        $this->assertSame([], $result['errors']);
    }

    /** @return array{rows: list<array<string,mixed>>, errors: list<array<string,mixed>>} */
    private function parse(string $content, string $ext): array
    {
        $path = sys_get_temp_dir() . '/ssfp_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $this->tmpFiles[] = $path;
        file_put_contents($path, $content);

        $file = new UploadedFile($path, 'sites.' . $ext, 'text/csv', null, true);

        $parser = new SpreadsheetSiteFileParser();
        $parser->validateFile($file);

        return $parser->parse($file);
    }
}
