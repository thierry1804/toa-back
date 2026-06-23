<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Entity\SitePrevention;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class KmzImportProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
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

        $plan = $data instanceof PlanPrevention
            ? $data
            : $this->entityManager->find(PlanPrevention::class, $uriVariables['id'] ?? null);

        if (!$plan instanceof PlanPrevention) {
            throw new UnprocessableEntityHttpException('plan_prevention.not_found');
        }

        $placemarks = $this->parseKmz($file->getPathname());

        foreach ($placemarks as $placemark) {
            $site = new SitePrevention();
            $site->setPlanPrevention($plan);
            $site->setNom($placemark['name']);
            $site->setLatitude($placemark['latitude']);
            $site->setLongitude($placemark['longitude']);
            $site->setSourceKmz(true);

            $this->entityManager->persist($site);
        }

        $this->entityManager->flush();

        return $plan;
    }

    /** @return array<int, array{name: string, latitude: float, longitude: float}> */
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

    /** @return array<int, array{name: string, latitude: float, longitude: float}> */
    private function parsePlacemarks(string $kmlContent): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($kmlContent);
        libxml_clear_errors();

        if ($xml === false) {
            throw new UnprocessableEntityHttpException('kmz_invalid_kml');
        }

        $xml->registerXPathNamespace('kml', 'http://www.opengis.net/kml/2.2');

        $placemarks = $xml->xpath('//kml:Placemark') ?: $xml->xpath('//Placemark') ?: [];

        $results = [];
        foreach ($placemarks as $placemark) {
            $name        = (string) ($placemark->name ?? 'Site sans nom');
            $coordinates = null;

            if (isset($placemark->Point->coordinates)) {
                $coordinates = trim((string) $placemark->Point->coordinates);
            } elseif (isset($placemark->MultiGeometry->Point->coordinates)) {
                $coordinates = trim((string) $placemark->MultiGeometry->Point->coordinates);
            }

            if ($coordinates === null || $coordinates === '') {
                continue;
            }

            $parts = explode(',', $coordinates);
            if (count($parts) < 2) {
                continue;
            }

            $results[] = [
                'name'      => $name,
                'longitude' => (float) $parts[0],
                'latitude'  => (float) $parts[1],
            ];
        }

        return $results;
    }
}
