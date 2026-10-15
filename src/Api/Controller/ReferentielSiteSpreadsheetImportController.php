<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\Referentiel\Entity\ImportSpreadsheetSite;
use App\Domain\Referentiel\Entity\Site;
use App\Domain\Referentiel\Repository\SiteRepository;
use App\Domain\Referentiel\Service\SpreadsheetSiteFileParser;
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

#[Route('/api/referentiel/sites/import-spreadsheet', name: 'referentiel_site_import_spreadsheet', methods: ['POST'])]
#[IsGranted(ReferentielSiteVoter::CREATE)]
class ReferentielSiteSpreadsheetImportController extends AbstractController
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
            throw new UnprocessableEntityHttpException('spreadsheet_file_required');
        }

        $parser = new SpreadsheetSiteFileParser();
        $parser->validateFile($file);

        ['rows' => $rows, 'errors' => $errors] = $parser->parse($file);

        $import = new ImportSpreadsheetSite();
        $import->setOriginalFilename($file->getClientOriginalName());
        $import->setNomFichier($file->getClientOriginalName());
        $this->entityManager->persist($import);

        $lookup = $this->siteRepository->buildLookupMapByCode();

        $sites = [];
        foreach ($rows as $row) {
            $key      = strtolower($row['codeSite']);
            $existing = $lookup[$key] ?? null;

            if ($existing !== null) {
                $existing->setNomSite($row['nomSite']);
                $existing->setLatitude($row['latitude']);
                $existing->setLongitude($row['longitude']);
                $existing->setAltitude($row['altitude']);
                $existing->setDescription($row['description']);
                $existing->setCouleurMarqueur($row['couleurMarqueur']);
                $existing->setFokontany($row['fokontany']);
                $existing->setCommune($row['commune']);
                $existing->setDistrict($row['district']);
                $existing->setTypeSite($row['typeSite']);
                $existing->setZone($row['zone']);
                $existing->setTypePylone($row['typePylone']);
                $existing->setHauteurPylone($row['hauteurPylone']);
                // Colonne absente ou vide (null) : on conserve le statut APN/API déjà connu du site.
                if ($row['apn'] !== null) {
                    $existing->setApn($row['apn']);
                }
                if ($row['api'] !== null) {
                    $existing->setApi($row['api']);
                }
                $existing->setImportSpreadsheet($import);
                $existing->setUpdatedAt(new \DateTime());
                $sites[] = $existing;
            } else {
                $site = new Site();
                $site->setCodeSite($row['codeSite']);
                $site->setNomSite($row['nomSite']);
                $site->setLatitude($row['latitude']);
                $site->setLongitude($row['longitude']);
                $site->setAltitude($row['altitude']);
                $site->setDescription($row['description']);
                $site->setCouleurMarqueur($row['couleurMarqueur']);
                $site->setFokontany($row['fokontany']);
                $site->setCommune($row['commune']);
                $site->setDistrict($row['district']);
                $site->setTypeSite($row['typeSite']);
                $site->setZone($row['zone']);
                $site->setTypePylone($row['typePylone']);
                $site->setHauteurPylone($row['hauteurPylone']);
                $site->setApn($row['apn'] ?? false);
                $site->setApi($row['api'] ?? false);
                $site->setSourceSpreadsheet(true);
                $site->setImportSpreadsheet($import);
                $this->entityManager->persist($site);
                $sites[]      = $site;
                $lookup[$key] = $site;
            }
        }

        $import->setNombreSites(count($sites));
        $this->entityManager->flush();

        $this->logger->info('referentiel_spreadsheet_import_completed', [
            'importId'  => (string) $import->getId(),
            'nbSites'   => count($sites),
            'nbErreurs' => count($errors),
        ]);

        return $this->json([
            'importId'     => (string) $import->getId(),
            'nomFichier'   => $import->getNomFichier(),
            'nombreSites'  => count($sites),
            'sitesIgnores' => count($errors),
            'errors'       => $errors,
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
