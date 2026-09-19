<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\Referentiel\Repository\SiteRepository;
use App\Security\Voter\ReferentielSiteVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/referentiel/sites/by-code/{code}', name: 'referentiel_site_by_code', methods: ['GET'])]
#[IsGranted(ReferentielSiteVoter::VIEW)]
class ReferentielSiteByCodeController extends AbstractController
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
    ) {
    }

    public function __invoke(string $code): JsonResponse
    {
        $site = $this->siteRepository->findByCodeSite($code);

        if ($site === null) {
            throw new NotFoundHttpException('site_not_found');
        }

        return $this->json([
            'codeSite' => $site->getCodeSite(),
            'nomSite'  => $site->getNomSite(),
            'apn'      => $site->isApn(),
            'api'      => $site->isApi(),
        ]);
    }
}
