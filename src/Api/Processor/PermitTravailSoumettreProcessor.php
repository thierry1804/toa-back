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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class PermitTravailSoumettreProcessor implements ProcessorInterface
{
    /** @var array<string, TypeDocumentPermitTravail[]> */
    private const DOCUMENT_MATRIX = [
        TypePermitTravail::GENERAL->value => [
            TypeDocumentPermitTravail::ATTESTATION_ENTREPRISE,
            TypeDocumentPermitTravail::LISTE_INTERVENANTS,
            TypeDocumentPermitTravail::PLAN_PREVENTION_REF,
        ],
        TypePermitTravail::ELECTRIQUE->value => [
            TypeDocumentPermitTravail::HABILITATION_ELECTRIQUE,
            TypeDocumentPermitTravail::CONSIGNATION_FICHE,
            TypeDocumentPermitTravail::ATTESTATION_BASSE_TENSION,
        ],
        TypePermitTravail::HAUTEUR->value => [
            TypeDocumentPermitTravail::CERTIFICAT_TRAVAIL_HAUTEUR,
            TypeDocumentPermitTravail::EPI_FICHE,
            TypeDocumentPermitTravail::PLAN_SAUVETAGE,
        ],
    ];

    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly EntityManagerInterface $entityManager,
        private readonly PermitTravailGroupeRepository $groupeRepository,
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
        if ($permit->getProcessus() === ProcessusPermitTravail::NOUVEAU_SITE) {
            $this->checkNouveauSitePair($permit);
        }

        $permit->setStatut(StatutPermitTravail::SOUMIS);
        $permit->setSoumisAt(new \DateTimeImmutable());

        if ($permit->isEngagementAccepte() && $permit->getEngagementAccepteAt() === null) {
            $permit->setEngagementAccepteAt(new \DateTimeImmutable());
        }

        return $this->persistProcessor->process($permit, $operation, $uriVariables, $context);
    }

    /** @return TypeDocumentPermitTravail[] */
    private function findMissingDocumentTypes(PermitTravail $permit): array
    {
        $required = self::DOCUMENT_MATRIX[$permit->getType()?->value ?? ''] ?? [];

        $presentTypes = array_map(
            static fn($doc) => $doc->getType(),
            $permit->getDocuments()->toArray(),
        );

        return array_values(array_filter(
            $required,
            static fn(TypeDocumentPermitTravail $req) => !in_array($req, $presentTypes, true),
        ));
    }

    private function checkNouveauSitePair(PermitTravail $permit): void
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
        }
    }
}
