<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Entity\VersionPermitTravail;
use App\Domain\PermitTravail\Enum\StatutPermitTravail;
use App\Domain\PermitTravail\Enum\TypeDocumentPermitTravail;
use App\Domain\PermitTravail\Repository\VersionPermitTravailRepository;
use App\Domain\PermitTravail\Service\PermitDocumentRequirementResolver;
use App\Domain\PermitTravail\Service\PermitTravailNotificationService;
use App\Domain\PlanPrevention\Enum\DecisionHse;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class PermitTravailResoumettreProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly EntityManagerInterface $entityManager,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly VersionPermitTravailRepository $versionRepository,
        private readonly PermitTravailNotificationService $notificationService,
        private readonly RequestStack $requestStack,
        private readonly PermitDocumentRequirementResolver $documentRequirementResolver,
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

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User) {
            throw new UnprocessableEntityHttpException('permit_travail.user_not_found');
        }

        if ($permit->getStatut() !== StatutPermitTravail::BROUILLON) {
            throw new UnprocessableEntityHttpException('permit_travail.non_modifiable');
        }

        if ($permit->getCreatedBy()?->getUserIdentifier() !== $user->getUserIdentifier()) {
            throw new UnprocessableEntityHttpException('permit_travail.non_modifiable');
        }

        $hasRefus = false;
        foreach ($permit->getDecisionsHse() as $decision) {
            if ($decision->getDecision() === DecisionHse::REFUSE) {
                $hasRefus = true;
                break;
            }
        }
        if (!$hasRefus) {
            throw new UnprocessableEntityHttpException('permit_travail.aucun_refus_enregistre');
        }

        if (!$permit->isEngagementAccepte()) {
            throw new UnprocessableEntityHttpException('permit_travail.engagement_obligatoire');
        }

        $missingTypes = $this->findMissingDocumentTypes($permit);
        if (!empty($missingTypes)) {
            throw new UnprocessableEntityHttpException(sprintf(
                'documents_manquants: %s',
                implode(', ', array_map(static fn(TypeDocumentPermitTravail $t) => $t->value, $missingTypes)),
            ));
        }

        $numeroVersion = $this->versionRepository->findNextNumeroVersion($permit);
        $motif = $this->requestStack->getCurrentRequest()?->request->get('motifResoumission');
        if ($motif === null || $motif === '') {
            $body = $this->parseRequestBody();
            $motif = $body['motifResoumission'] ?? null;
        }

        $version = new VersionPermitTravail();
        $version->setPermitTravail($permit);
        $version->setNumeroVersion($numeroVersion);
        $version->setSnapshotData($this->buildSnapshot($permit));
        $version->setCreatedBy($user);
        $version->setCreatedAt(new \DateTimeImmutable());
        $version->setMotifResoumission($motif !== '' && $motif !== null ? $motif : null);

        $this->entityManager->persist($version);

        $permit->setStatut(StatutPermitTravail::SOUMIS);
        $permit->setSoumisAt(new \DateTimeImmutable());
        $permit->setValidatedAt(null);

        $result = $this->persistProcessor->process($permit, $operation, $uriVariables, $context);

        $this->notificationService->notifierHseResoumission($permit, $numeroVersion);

        return $result;
    }

    /** @return TypeDocumentPermitTravail[] */
    private function findMissingDocumentTypes(PermitTravail $permit): array
    {
        return $this->documentRequirementResolver->findMissingDocumentTypes($permit);
    }

    private function buildSnapshot(PermitTravail $permit): array
    {
        return [
            'reference'         => $permit->getReference(),
            'codeSite'          => $permit->getCodeSite(),
            'typePermis'        => $permit->getType()?->value,
            'processus'         => $permit->getProcessus()?->value,
            'statut'            => $permit->getStatut()->value,
            'descriptionTravaux' => $permit->getDescriptionTravaux(),
            'dateDebutPrevue'   => $permit->getDateDebutPrevue()?->format(\DateTimeInterface::ATOM),
            'dateFinPrevue'     => $permit->getDateFinPrevue()?->format(\DateTimeInterface::ATOM),
            'documents'         => array_map(
                static fn($d) => [
                    'type'     => $d->getType()->value,
                    'filePath' => $d->getFilePath(),
                ],
                $permit->getDocuments()->toArray(),
            ),
            'decisionsHse'      => array_map(
                static fn($d) => [
                    'decision'    => $d->getDecision()?->value,
                    'commentaire' => $d->getCommentaire(),
                    'decidedAt'   => $d->getDecidedAt()?->format(\DateTimeInterface::ATOM),
                ],
                $permit->getDecisionsHse()->toArray(),
            ),
        ];
    }

    private function parseRequestBody(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return [];
        }

        $content = $request->getContent();
        if ($content === '') {
            return [];
        }

        $data = json_decode($content, true);

        return is_array($data) ? $data : [];
    }
}
