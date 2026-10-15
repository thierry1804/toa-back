<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\PermitTravail\Entity\DecisionHsePermitTravail;
use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Enum\ProcessusPermitTravail;
use App\Domain\PermitTravail\Enum\StatutPermitTravail;
use App\Domain\PermitTravail\Service\PermitTravailNotificationService;
use App\Domain\PlanPrevention\Enum\DecisionHse;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class PermitTravailValiderProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RequestStack $requestStack,
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

        if ($permit->getStatut() !== StatutPermitTravail::SOUMIS) {
            throw new UnprocessableEntityHttpException('permit_travail.statut_not_soumis');
        }

        $body = $this->parseRequestBody();

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User) {
            throw new UnprocessableEntityHttpException('permit_travail.user_not_found');
        }

        $signatureElectronique = substr(
            hash('sha256', implode('|', [
                $user->getUserIdentifier(),
                $permit->getId()?->toRfc4122() ?? '',
                (new \DateTimeImmutable())->format('Y-m-d\TH:i:sP'),
            ])),
            0,
            64
        );

        $decision = new DecisionHsePermitTravail();
        $decision->setPermitTravail($permit);
        $decision->setDecidePar($user);
        $decision->setDecision(DecisionHse::VALIDE);
        $decision->setCommentaire($body['commentaire'] ?? null);
        $decision->setSignatureElectronique($signatureElectronique);
        $now = new \DateTimeImmutable();
        $decision->setDecidedAt($now);

        $permit->setStatut(StatutPermitTravail::VALIDE_HSE);
        $permit->setValidatedAt($now);
        // R-19/R-20 : la date de début réelle est la date de validation (non
        // saisissable) ; la validité court 7 jours pour « Nouveau site », 3
        // jours pour les autres processus, à compter de cette validation.
        $dureeJours = $permit->getProcessus() === ProcessusPermitTravail::NOUVEAU_SITE ? 7 : 3;
        $permit->setDateDebutPrevue($now);
        $permit->setDateFinPrevue($now->modify(sprintf('+%d days', $dureeJours)));
        $permit->addDecisionHse($decision);

        $this->entityManager->persist($decision);
        $this->entityManager->flush();

        $this->notificationService->notifierValidation($permit);

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
