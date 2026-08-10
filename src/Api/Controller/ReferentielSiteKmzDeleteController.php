<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\PlanPrevention\Entity\SitePrevention;
use App\Domain\Referentiel\Entity\ImportKmzSite;
use App\Domain\Referentiel\Repository\SiteRepository;
use App\Security\Voter\ReferentielSiteVoter;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/api/referentiel/import-kmz/{id}', name: 'referentiel_import_kmz_delete', methods: ['DELETE'])]
#[IsGranted(ReferentielSiteVoter::DELETE)]
class ReferentielSiteKmzDeleteController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SiteRepository $siteRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        $uuid   = Uuid::fromString($id);
        $import = $this->entityManager->find(ImportKmzSite::class, $uuid);

        if (!$import instanceof ImportKmzSite) {
            throw new NotFoundHttpException('import_kmz_site.not_found');
        }

        $sites = $this->siteRepository->findByImportKmz($import);

        $sitePreventionRepo = $this->entityManager->getRepository(SitePrevention::class);

        foreach ($sites as $site) {
            $sitesPreventions = $sitePreventionRepo->findBy(['site' => $site]);
            foreach ($sitesPreventions as $sp) {
                $this->entityManager->remove($sp);
            }
            $this->entityManager->remove($site);
        }

        $this->entityManager->remove($import);
        $this->entityManager->flush();

        $this->logger->info('referentiel_import_kmz_deleted', [
            'importId'  => $id,
            'nbSites'   => count($sites),
        ]);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
