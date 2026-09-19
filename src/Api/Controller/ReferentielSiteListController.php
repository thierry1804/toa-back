<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\Referentiel\Repository\SiteRepository;
use App\Security\Voter\ReferentielSiteVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/referentiel/sites', name: 'referentiel_site_list', methods: ['GET'])]
#[IsGranted(ReferentielSiteVoter::VIEW)]
class ReferentielSiteListController extends AbstractController
{
    private const PAGE_SIZE     = 20;
    private const MAX_LIMIT     = 1000;

    public function __construct(
        private readonly SiteRepository $siteRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $page  = max(1, (int) $request->query->get('page', 1));
        $limit = min(self::MAX_LIMIT, max(1, (int) $request->query->get('limit', self::PAGE_SIZE)));
        $offset = ($page - 1) * $limit;

        $qb = $this->siteRepository->createQueryBuilder('s')
            ->leftJoin('s.importKmz', 'i')
            ->addSelect('i')
            ->orderBy('s.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $sites = $qb->getQuery()->getResult();

        $totalQb = $this->siteRepository->createQueryBuilder('s')
            ->select('COUNT(s.id)');
        $total = (int) $totalQb->getQuery()->getSingleScalarResult();

        $data = array_map(static fn ($site): array => [
            'id'              => (string) $site->getId(),
            'codeSite'        => $site->getCodeSite(),
            'nomSite'         => $site->getNomSite(),
            'latitude'        => $site->getLatitude(),
            'longitude'       => $site->getLongitude(),
            'altitude'        => $site->getAltitude(),
            'description'     => $site->getDescription(),
            'couleurMarqueur' => $site->getCouleurMarqueur(),
            'region'          => $site->getRegion(),
            'fokontany'       => $site->getFokontany(),
            'commune'         => $site->getCommune(),
            'district'        => $site->getDistrict(),
            'situation'       => $site->getSituation(),
            'apn'             => $site->isApn(),
            'api'             => $site->isApi(),
            'sourceKmz'       => $site->isSourceKmz(),
            'createdAt'       => $site->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt'       => $site->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            'importKmz'       => $site->getImportKmz() !== null ? [
                'id'         => (string) $site->getImportKmz()->getId(),
                'nomFichier' => $site->getImportKmz()->getNomFichier(),
            ] : null,
        ], $sites);

        return $this->json([
            'member'     => $data,
            'totalItems' => $total,
        ]);
    }
}
