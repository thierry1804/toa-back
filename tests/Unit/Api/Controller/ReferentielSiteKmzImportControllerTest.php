<?php

declare(strict_types=1);

namespace App\Tests\Unit\Api\Controller;

use App\Api\Controller\ReferentielSiteKmzImportController;
use App\Domain\Referentiel\Entity\Site;
use App\Domain\Referentiel\Repository\SiteRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

class ReferentielSiteKmzImportControllerTest extends TestCase
{
    /** @var list<string> */
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $file) {
            @unlink($file);
        }
    }

    public function testReimportWithoutApnApiColumnsKeepsExistingFlags(): void
    {
        $existing = $this->existingSite(apn: true, api: true);

        $this->import($this->kml(''), $existing);

        $this->assertTrue($existing->isApn());
        $this->assertTrue($existing->isApi());
    }

    public function testBlankCellsAreTreatedAsAbsentColumns(): void
    {
        $existing = $this->existingSite(apn: true, api: false);

        $this->import($this->kml('<Data name="APN"><value> </value></Data><Data name="API"><value></value></Data>'), $existing);

        $this->assertTrue($existing->isApn());
        $this->assertFalse($existing->isApi());
    }

    public function testExplicitValuesOverwriteExistingFlags(): void
    {
        $existing = $this->existingSite(apn: true, api: false);

        $this->import($this->kml('<Data name="APN"><value>0</value></Data><Data name="API"><value>1</value></Data>'), $existing);

        $this->assertFalse($existing->isApn());
        $this->assertTrue($existing->isApi());
    }

    public function testNewSiteWithoutColumnsDefaultsToFalse(): void
    {
        $persisted = [];
        $this->import($this->kml(''), null, $persisted);

        $created = array_values(array_filter($persisted, static fn (object $o): bool => $o instanceof Site));
        $this->assertCount(1, $created);
        $this->assertFalse($created[0]->isApn());
        $this->assertFalse($created[0]->isApi());
    }

    private function existingSite(bool $apn, bool $api): Site
    {
        return (new Site())
            ->setNomSite('Ankazobe')
            ->setCodeSite('ANK-001')
            ->setCommune('C1')
            ->setDistrict('D1')
            ->setApn($apn)
            ->setApi($api);
    }

    private function kml(string $extraData): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><kml><Document><Placemark><name>Ankazobe</name>'
            . '<ExtendedData><Data name="commune"><value>C1</value></Data><Data name="district"><value>D1</value></Data>'
            . $extraData . '</ExtendedData>'
            . '<Point><coordinates>47.5,-18.9,1200</coordinates></Point></Placemark></Document></kml>';
    }

    /** @param list<object> $persisted */
    private function import(string $kml, ?Site $existing, array &$persisted = []): void
    {
        $path = tempnam(sys_get_temp_dir(), 'kmz');
        $this->tmpFiles[] = $path;
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('doc.kml', $kml);
        $zip->close();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $lookup = $existing !== null ? ['Ankazobe|C1|D1' => $existing] : [];
        $repository = $this->createMock(SiteRepository::class);
        $repository->method('buildLookupMap')->willReturn($lookup);

        $controller = new ReferentielSiteKmzImportController($entityManager, $repository, new NullLogger());
        $controller->setContainer(new Container());

        $file = new UploadedFile($path, 'sites.kmz', 'application/zip', null, true);
        $controller(new Request(files: ['file' => $file]));
    }
}
