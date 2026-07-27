<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Intervention\Entity\EvaluationRisque;
use App\Domain\Intervention\Entity\Intervention;
use App\Domain\Intervention\Entity\SuiviJournalier;
use App\Domain\PermitTravail\Entity\DecisionCdpPvReception;
use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Enum\DecisionCdpPv;
use App\Domain\PermitTravail\Enum\StatutPermitTravail;
use App\Domain\PermitTravail\Enum\StatutPvReceptionPdf;
use App\Domain\PermitTravail\Message\UpdateKpisMessage;
use App\Domain\PermitTravail\Repository\PvReceptionPdfRepository;
use App\Domain\PermitTravail\Service\PermitTravailNotificationService;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class PvValiderProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly PvReceptionPdfRepository $pvRepository,
        private readonly MessageBusInterface $bus,
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

        $pv = $this->pvRepository->findByPermitTravailId((string) $permit->getId());
        if ($pv === null || $pv->getStatut() !== StatutPvReceptionPdf::GENERE) {
            throw new UnprocessableEntityHttpException('permit_travail.pv_not_genere');
        }

        $this->checkSuiviAndEvaluation($permit);

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
        $decision->setDecision(DecisionCdpPv::VALIDE);
        $decision->setSignatureElectronique($signatureElectronique);
        $decision->setDecidedAt($now);

        $permit->setStatut(StatutPermitTravail::PV_VALIDE);

        $this->entityManager->persist($decision);
        $this->entityManager->flush();

        $this->notificationService->notifierHseArchivage($permit, $now);

        $this->bus->dispatch(new UpdateKpisMessage(
            permitTravailId: $permit->getId()?->toRfc4122() ?? '',
            type: 'PV_VALIDE',
            decidedAt: $now->format(\DateTimeInterface::ATOM),
        ));

        return $permit;
    }

    private function checkSuiviAndEvaluation(PermitTravail $permit): void
    {
        $intervention = $this->entityManager->getRepository(Intervention::class)
            ->findOneBy(['permitTravail' => $permit]);

        if ($intervention === null) {
            throw new UnprocessableEntityHttpException('permit_travail.intervention_manquante');
        }

        $interventionId = $intervention->getId()->toRfc4122();

        $suiviCount = (int) $this->entityManager->createQuery(
            'SELECT COUNT(s) FROM ' . SuiviJournalier::class . ' s WHERE s.intervention = :id'
        )->setParameter('id', $interventionId)->getSingleScalarResult();

        if ($suiviCount === 0) {
            throw new UnprocessableEntityHttpException('permit_travail.suivi_journalier_manquant');
        }

        $evalCount = (int) $this->entityManager->createQuery(
            'SELECT COUNT(e) FROM ' . EvaluationRisque::class . ' e WHERE e.intervention = :id'
        )->setParameter('id', $interventionId)->getSingleScalarResult();

        if ($evalCount === 0) {
            throw new UnprocessableEntityHttpException('permit_travail.evaluation_risque_manquante');
        }
    }
}
