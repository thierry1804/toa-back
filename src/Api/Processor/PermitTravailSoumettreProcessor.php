<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Enum\ProcessusPermitTravail;
use App\Domain\PermitTravail\Enum\StatutPermitTravail;
use App\Domain\PermitTravail\Enum\TypeDocumentPermitTravail;
use App\Domain\PermitTravail\Enum\TypePermitTravail;
use App\Domain\PermitTravail\Repository\PermitTravailGroupeRepository;
use App\Domain\PermitTravail\Service\PermitDocumentRequirementResolver;
use App\Domain\PermitTravail\Service\PermitTravailNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class PermitTravailSoumettreProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly EntityManagerInterface $entityManager,
        private readonly PermitTravailGroupeRepository $groupeRepository,
        private readonly PermitDocumentRequirementResolver $documentRequirementResolver,
        private readonly PermitTravailNotificationService $notificationService,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $permit = $data instanceof PermitTravail
            ? $data
            : $this->entityManager->find(PermitTravail::class, $uriVariables['id'] ?? null);

        if (!$permit instanceof PermitTravail) {
            throw new UnprocessableEntityHttpException('permit_travail.not_found');
        }

        // RG3: engagement must be accepted
        if (!$permit->isEngagementAccepte()) {
            throw new UnprocessableEntityHttpException('permit_travail.engagement_obligatoire');
        }

        $missingTypes = $this->findMissingDocumentTypes($permit);
        if (!empty($missingTypes)) {
            $missingLabels = array_map(
                static fn(TypeDocumentPermitTravail $t) => $t->value,
                $missingTypes,
            );

            throw new UnprocessableEntityHttpException(
                sprintf('documents_manquants: %s', implode(', ', $missingLabels)),
            );
        }

        // NOUVEAU_SITE: both GENERAL + specialise must be submitted together
        $companion = null;
        if ($permit->getProcessus() === ProcessusPermitTravail::NOUVEAU_SITE) {
            $companion = $this->checkNouveauSitePair($permit);
        } else {
            $this->checkGeneralRequis($permit);
        }

        $permit->setStatut(StatutPermitTravail::SOUMIS);
        $permit->setSoumisAt(new \DateTimeImmutable());

        if ($permit->isEngagementAccepte() && $permit->getEngagementAccepteAt() === null) {
            $permit->setEngagementAccepteAt(new \DateTimeImmutable());
        }

        $result = $this->persistProcessor->process($permit, $operation, $uriVariables, $context);

        $this->notificationService->notifierSoumission($permit);
        if ($companion !== null) {
            $this->notificationService->notifierSoumission($companion);
        }

        return $result;
    }

    /** @return TypeDocumentPermitTravail[] */
    private function findMissingDocumentTypes(PermitTravail $permit): array
    {
        return $this->documentRequirementResolver->findMissingDocumentTypes($permit);
    }

    /**
     * Hors « Nouveau site », un permis spécialisé ne peut être soumis que si un
     * permis Général du même couple site/plan a déjà été soumis.
     */
    private function checkGeneralRequis(PermitTravail $permit): void
    {
        if ($permit->getType() === TypePermitTravail::GENERAL) {
            return;
        }

        $generaux = $this->entityManager->getRepository(PermitTravail::class)->findBy([
            'codeSite'       => $permit->getCodeSite(),
            'planPrevention' => $permit->getPlanPrevention(),
            'type'           => TypePermitTravail::GENERAL,
        ]);

        $nonSoumis = [StatutPermitTravail::BROUILLON, StatutPermitTravail::REJETE, StatutPermitTravail::REFUSE_HSE];
        foreach ($generaux as $general) {
            if (!in_array($general->getStatut(), $nonSoumis, true)) {
                return;
            }
        }

        throw new UnprocessableEntityHttpException('permit_travail.general_requis');
    }

    private function checkNouveauSitePair(PermitTravail $permit): ?PermitTravail
    {
        $groupe = $this->groupeRepository->findByCodeSiteAndPlan(
            $permit->getCodeSite(),
            $permit->getPlanPrevention(),
        );

        if ($groupe === null || $groupe->getPermitSpecialise() === null) {
            throw new UnprocessableEntityHttpException('permit_travail.deux_permis_requis_nouveau_site');
        }

        $other = $groupe->getPermitGeneral()?->getId() === $permit->getId()
            ? $groupe->getPermitSpecialise()
            : $groupe->getPermitGeneral();

        if ($other === null) {
            throw new UnprocessableEntityHttpException('permit_travail.deux_permis_requis_nouveau_site');
        }

        if ($other->getStatut() === StatutPermitTravail::BROUILLON) {
            // Companion is still a draft — auto-submit it in the same transaction
            // if it has all required documents and engagement accepted.
            $missingCompanion = $this->findMissingDocumentTypes($other);
            if (!empty($missingCompanion)) {
                throw new UnprocessableEntityHttpException('permit_travail.deux_permis_requis_nouveau_site');
            }

            if (!$other->isEngagementAccepte()) {
                throw new UnprocessableEntityHttpException('permit_travail.deux_permis_requis_nouveau_site');
            }

            $other->setStatut(StatutPermitTravail::SOUMIS);
            $other->setSoumisAt(new \DateTimeImmutable());
            if ($other->getEngagementAccepteAt() === null) {
                $other->setEngagementAccepteAt(new \DateTimeImmutable());
            }

            return $other;
        }

        return null;
    }
}
