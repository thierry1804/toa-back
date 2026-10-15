<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\Referentiel\Repository\SiteRepository;
use App\Domain\Referentiel\Service\SpreadsheetSiteFileParser;
use App\Security\Voter\ReferentielSiteVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Aperçu (sans persistance) d'un import CSV/XLSX : indique quels sites seraient créés,
 * mis à jour, ou en erreur, avant que l'utilisateur ne confirme l'import réel.
 */
#[Route('/api/referentiel/sites/import-spreadsheet/preview', name: 'referentiel_site_import_spreadsheet_preview', methods: ['POST'])]
#[IsGranted(ReferentielSiteVoter::CREATE)]
class ReferentielSiteSpreadsheetPreviewController extends AbstractController
{
    public function __construct(private readonly SiteRepository $siteRepository)
    {
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

        $lookup   = $this->siteRepository->buildLookupMapByCode();
        $toCreate = [];
        $toUpdate = [];

        foreach ($rows as $row) {
            $key      = strtolower($row['codeSite']);
            $existing = $lookup[$key] ?? null;

            $summary = [
                'codeSite' => $row['codeSite'],
                'nomSite'  => $row['nomSite'],
                'commune'  => $row['commune'],
                'district' => $row['district'],
            ];

            if ($existing !== null) {
                $toUpdate[] = $summary;
            } else {
                $toCreate[] = $summary;
            }
        }

        return $this->json([
            'toCreate' => $toCreate,
            'toUpdate' => $toUpdate,
            'errors'   => $errors,
        ]);
    }
}
