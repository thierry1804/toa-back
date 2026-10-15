<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\Referentiel\Repository\SiteRepository;
use App\Domain\Referentiel\Service\KmzSiteFileParser;
use App\Security\Voter\ReferentielSiteVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Aperçu (sans persistance) d'un import KMZ : indique quels sites seraient créés,
 * mis à jour, ou ignorés, avant que l'utilisateur ne confirme l'import réel.
 */
#[Route('/api/referentiel/sites/import-kmz/preview', name: 'referentiel_site_import_kmz_preview', methods: ['POST'])]
#[IsGranted(ReferentielSiteVoter::CREATE)]
class ReferentielSiteKmzPreviewController extends AbstractController
{
    public function __construct(private readonly SiteRepository $siteRepository)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        if ($file === null) {
            throw new UnprocessableEntityHttpException('kmz_file_required');
        }

        $parser = new KmzSiteFileParser();
        $parser->validateFile($file);

        ['placemarks' => $placemarks, 'ignored' => $ignored] = $parser->parse($file->getPathname());

        $lookup       = $this->siteRepository->buildLookupMap();
        $seenInBatch  = [];
        $toCreate     = [];
        $toUpdate     = [];

        foreach ($placemarks as $placemark) {
            $key      = $placemark['name']
                . '|' . ($placemark['commune'] ?? '')
                . '|' . ($placemark['district'] ?? '');
            $existing = $lookup[$key] ?? null;

            $summary = [
                'codeSite' => $placemark['codeSite'] ?? ($existing?->getCodeSite() ?? $placemark['name']),
                'nomSite'  => $placemark['name'],
                'commune'  => $placemark['commune'],
                'district' => $placemark['district'],
            ];

            if ($existing !== null || isset($seenInBatch[$key])) {
                $toUpdate[] = $summary;
            } else {
                $toCreate[] = $summary;
                // Une même clé répétée dans le fichier doit être traitée comme une mise à jour
                // du site qui sera créé par la ligne précédente (même comportement que l'import réel).
                $seenInBatch[$key] = true;
            }
        }

        return $this->json([
            'toCreate' => $toCreate,
            'toUpdate' => $toUpdate,
            'errors'   => $ignored,
        ]);
    }
}
