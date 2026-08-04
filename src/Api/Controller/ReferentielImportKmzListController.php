<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\Referentiel\Repository\ImportKmzSiteRepository;
use App\Security\Voter\ReferentielSiteVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/referentiel/import-kmz', name: 'referentiel_import_kmz_list', methods: ['GET'])]
#[IsGranted(ReferentielSiteVoter::VIEW)]
class ReferentielImportKmzListController extends AbstractController
{
    public function __construct(private readonly ImportKmzSiteRepository $repository) {}

    public function __invoke(Request $request): JsonResponse
    {
        $imports = $this->repository->findBy([], ['importedAt' => 'DESC']);

        $data = array_map(static fn ($import): array => [
            'id'               => (string) $import->getId(),
            'nomFichier'       => $import->getNomFichier(),
            'originalFilename' => $import->getOriginalFilename(),
            'importedAt'       => $import->getImportedAt()->format(\DateTimeInterface::ATOM),
            'nombreSites'      => $import->getNombreSites(),
        ], $imports);

        return $this->json([
            'member'     => $data,
            'totalItems' => count($data),
        ]);
    }
}
