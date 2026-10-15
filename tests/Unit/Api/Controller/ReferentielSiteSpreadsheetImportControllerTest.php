<?php

declare(strict_types=1);

namespace App\Tests\Unit\Api\Controller;

use App\Api\Controller\ReferentielSiteSpreadsheetImportController;
use App\Domain\Referentiel\Entity\Site;
use App\Domain\Referentiel\Repository\SiteRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

class ReferentielSiteSpreadsheetImportControllerTest extends TestCase
{
    /** @var list<string> */
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $file) {
            @unlink($file);
        }
    }

    public function testCreatesNewSiteWhenCodeSiteUnknown(): void
    {
        $persisted = [];
        $this->import("codeSite,nomSite,latitude,longitude\nSITE-001,Ankazobe,-18.9,47.5\n", [], $persisted);

        $created = array_values(array_filter($persisted, static fn (object $o): bool => $o instanceof Site));
        $this->assertCount(1, $created);
        $this->assertSame('SITE-001', $created[0]->getCodeSite());
        $this->assertTrue($created[0]->isSourceSpreadsheet());
    }

    public function testUpdatesExistingSiteWhenCodeSiteMatches(): void
    {
        $existing = (new Site())->setCodeSite('SITE-001')->setNomSite('Old name');

        $this->import("codeSite,nomSite,latitude,longitude\nSITE-001,New name,-18.9,47.5\n", ['site-001' => $existing]);

        $this->assertSame('New name', $existing->getNomSite());
    }

    public function testKeepsExistingApnApiWhenColumnsBlank(): void
    {
        $existing = (new Site())->setCodeSite('SITE-001')->setNomSite('Ankazobe')->setApn(true)->setApi(true);

        $this->import("codeSite,nomSite,latitude,longitude,apn,api\nSITE-001,Ankazobe,-18.9,47.5,,\n", ['site-001' => $existing]);

        $this->assertTrue($existing->isApn());
        $this->assertTrue($existing->isApi());
    }

    /**
     * @param array<string, Site> $lookup
     * @param list<object> $persisted
     */
    private function import(string $csv, array $lookup, array &$persisted = []): void
    {
        $path = sys_get_temp_dir() . '/sic_' . bin2hex(random_bytes(8)) . '.csv';
        $this->tmpFiles[] = $path;
        file_put_contents($path, $csv);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $repository = $this->createMock(SiteRepository::class);
        $repository->method('buildLookupMapByCode')->willReturn($lookup);

        $controller = new ReferentielSiteSpreadsheetImportController($entityManager, $repository, new NullLogger());
        $controller->setContainer(new Container());

        $file = new UploadedFile($path, 'sites.csv', 'text/csv', null, true);
        $controller(new Request(files: ['file' => $file]));
    }
}
