<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\PermitTravail\Entity\DecisionCdpPvReception;
use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Enum\DecisionCdpPv;
use App\Domain\PermitTravail\Enum\StatutPermitTravail;
use App\Domain\PermitTravail\Service\PermitTravailNotificationService;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class PvRefuserProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RequestStack $requestStack,
        private readonly PermitTravailNotificationService $notificationService,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $permit = $data instanceof PermitTravail
            ? $data
            : $this->entityManager->find(PermitTravail::class, $uriVariables['id'] ?? null);

        if (!$permit instanceof PermitTravail) {
            throw new UnprocessableEntityHttpException('permit_travail.not_found');
        }

        if ($permit->getStatut() !== StatutPermitTravail::CLOTURE) {
            throw new UnprocessableEntityHttpException('permit_travail.not_cloture');
        }

        $body        = $this->parseRequestBody();
        $commentaire = trim((string) ($body['commentaire'] ?? ''));

        if ($commentaire === '') {
            throw new UnprocessableEntityHttpException('permit_travail.commentaire_obligatoire');
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User) {
            throw new UnprocessableEntityHttpException('permit_travail.user_not_found');
        }

        $now = new \DateTimeImmutable();
        $signatureElectronique = substr(
            hash('sha256', implode('|', [
                $user->getUserIdentifier(),
                $permit->getId()?->toRfc4122() ?? '',
                $now->format('Y-m-d\TH:i:sP'),
            ])),
            0,
            64
        );

        $decision = new DecisionCdpPvReception();
        $decision->setPermitTravail($permit);
        $decision->setDecidePar($user);
        $decision->setDecision(DecisionCdpPv::REFUSE);
        $decision->setCommentaire($commentaire);
        $decision->setSignatureElectronique($signatureElectronique);
        $decision->setDecidedAt($now);

        // RG2: workflow retour — prestataire peut re-clôturer
        $permit->setStatut(StatutPermitTravail::CLOTURE);

        $this->entityManager->persist($decision);
        $this->entityManager->flush();

        $this->notificationService->notifierPrestatairePvRefus($permit, $commentaire);

        return $permit;
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
