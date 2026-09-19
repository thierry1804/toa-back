<?php

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\ActivityPlanning\Entity\ActivityPlanning;
use App\Domain\ActivityPlanning\Message\ActivityPlanningUpdatedNotification;
use App\Domain\ActivityPlanning\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class ActivityPlanningUpdateProcessor implements ProcessorInterface
{
    private const ALL_FIELDS = [
        'process', 'provider', 'providerEmail',
        'siteCode', 'siteName',
        'expectedStartDate', 'expectedEndDate',
        'status', 'permitReference', 'permitValidated',
        'typeIntervention',
    ];

    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private EntityManagerInterface $entityManager,
        private AuditLogger $auditLogger,
        private TokenStorageInterface $tokenStorage,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof ActivityPlanning) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        // API Platform creates a new instance during deserialization.
        // Fetch the managed entity and apply changes to it.
        $existing = $this->entityManager->getRepository(ActivityPlanning::class)->find($uriVariables['id'] ?? $data->getId());
        if (!$existing) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $originalData = $this->entityManager->getUnitOfWork()->getOriginalEntityData($existing);

        // Apply only fields that differ from the original entity data.
        // This prevents a fresh deserialized $data from overwriting fields
        // with PHP default values (e.g. status → 'planifie', permitValidated → false)
        // when those fields were not present in the PATCH request.
        foreach (self::ALL_FIELDS as $field) {
            $setter = 'set' . ucfirst($field);
            $getter = 'get' . ucfirst($field);
            if (!method_exists($data, $getter) || !method_exists($existing, $setter)) {
                continue;
            }

            $newValue = $data->$getter();
            $oldValue = $originalData[$field] ?? null;

            $newStr = $newValue instanceof \DateTimeInterface
                ? $newValue->format('c')
                : (string) $newValue;
            $oldStr = $oldValue instanceof \DateTimeInterface
                ? $oldValue->format('c')
                : (string) $oldValue;

            if ($newStr !== $oldStr) {
                $existing->$setter($newValue);
            }
        }

        // sites is a JSON array — compare serialized form
        $newSites = $data->getSites();
        if ($newSites !== null && json_encode($newSites) !== json_encode($existing->getSites())) {
            $existing->setSites($newSites);
        }

        $this->applyActualDates($existing, $originalData);
        $this->checkLockedFields($existing, $originalData);

        $result = $this->persistProcessor->process($existing, $operation, $uriVariables, $context);

        $this->logAudit($existing, $originalData);

        $this->dispatchNotification($existing, $originalData);

        return $result;
    }

    private function applyActualDates(ActivityPlanning $planning, array $originalData): void
    {
        $previousStatus = $originalData['status'] ?? null;
        $newStatus = $planning->getStatus();

        if ($previousStatus === $newStatus) {
            return;
        }

        $now = new \DateTimeImmutable();

        if ($newStatus === ActivityPlanning::STATUS_EN_COURS && $planning->getActualStartDate() === null) {
            $planning->setActualStartDate($now);
        }

        if (
            in_array($newStatus, [ActivityPlanning::STATUS_VALIDE, ActivityPlanning::STATUS_ANNULE], true)
            && $planning->getActualEndDate() === null
        ) {
            $planning->setActualEndDate($now);
        }
    }

    private function checkLockedFields(ActivityPlanning $planning, array $originalData): void
    {
        $originalStatus = $originalData['status'] ?? null;
        $originalPermitValidated = (bool) ($originalData['permitValidated'] ?? false);

        $wasLocked = in_array($originalStatus, [
            ActivityPlanning::STATUS_EN_COURS,
            ActivityPlanning::STATUS_VALIDE,
        ], true);

        if (!$wasLocked && !$originalPermitValidated) {
            return;
        }

        // Compute locked fields from the ORIGINAL entity state (pre-changes).
        // Mirrors ActivityPlanning::getLockedFields() but uses original values.
        $lockedFields = [];

        if ($wasLocked) {
            $lockedFields = [
                'process', 'siteCode', 'siteName',
                'expectedStartDate', 'expectedEndDate',
                'typeIntervention',
            ];
        }

        if ($originalPermitValidated) {
            $lockedFields[] = 'provider';
        }

        $changedLocked = [];

        foreach ($lockedFields as $field) {
            $getter = 'get' . ucfirst($field);
            if (!method_exists($planning, $getter)) {
                continue;
            }
            $newValue = $planning->$getter();
            $oldValue = $originalData[$field] ?? null;

            $newStr = $newValue instanceof \DateTimeInterface
                ? $newValue->format('c')
                : (string) $newValue;
            $oldStr = $oldValue instanceof \DateTimeInterface
                ? $oldValue->format('c')
                : (string) $oldValue;

            if ($newStr !== $oldStr) {
                $changedLocked[] = $field;
            }
        }

        if (!empty($changedLocked)) {
            throw new UnprocessableEntityHttpException('locked_fields_not_modifiable');
        }
    }

    private function logAudit(ActivityPlanning $planning, array $originalData): void
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        $changedBy = $user instanceof \App\Domain\User\Entity\User
            ? $user->getUserIdentifier()
            : 'system';

        $this->auditLogger->logChanges($planning, $originalData, $changedBy);
        $this->entityManager->flush();
    }

    private function dispatchNotification(ActivityPlanning $planning, array $originalData): void
    {
        $changes = [];
        $trackedFields = [
            'process', 'provider',
            'siteCode', 'siteName',
            'expectedStartDate', 'expectedEndDate',
            'status',
        ];

        foreach ($trackedFields as $field) {
            $getter = 'get' . ucfirst($field);
            if (!method_exists($planning, $getter)) {
                continue;
            }
            $newValue = $planning->$getter();
            $oldValue = $originalData[$field] ?? null;

            $newStr = $newValue instanceof \DateTimeInterface
                ? $newValue->format('d/m/Y')
                : (string) $newValue;
            $oldStr = $oldValue instanceof \DateTimeInterface
                ? $oldValue->format('d/m/Y')
                : (string) $oldValue;

            if ($newStr !== $oldStr) {
                $changes[$field] = ['old' => $oldStr, 'new' => $newStr];
            }
        }

        if (empty($changes)) {
            return;
        }

        $providerEmail = $planning->getProviderEmail();

        try {
            $this->messageBus->dispatch(new ActivityPlanningUpdatedNotification(
                planningId: $planning->getId() ?? 0,
                providerEmail: $providerEmail ?? '',
                providerName: $planning->getProvider() ?? '',
                process: $planning->getProcess() ?? '',
                changes: $changes,
            ));
        } catch (\Throwable $e) {
            $this->logger->error('[Planning] Échec dispatch notification de modification', [
                'planningId' => $planning->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
