<?php

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\ActivityPlanning\Entity\ActivityPlanning;
use App\Domain\ActivityPlanning\Message\ActivityPlanningUpdatedNotification;
use App\Domain\ActivityPlanning\Service\AuditLogger;
use App\Domain\ActivityPlanning\Service\ConflictDetector;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class ActivityPlanningUpdateProcessor implements ProcessorInterface
{
    private const DATE_FIELDS = [
        'theoreticalStartDate', 'expectedStartDate', 'expectedEndDate',
    ];

    private const ALL_FIELDS = [
        'process', 'provider', 'providerEmail', 'projectDescription',
        'siteCode', 'siteNumber', 'siteName', 'region',
        'theoreticalStartDate', 'expectedStartDate', 'expectedEndDate',
        'status', 'permitReference', 'permitValidated',
    ];

    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private EntityManagerInterface $entityManager,
        private ConflictDetector $conflictDetector,
        private AuditLogger $auditLogger,
        private TokenStorageInterface $tokenStorage,
        private MessageBusInterface $messageBus,
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
        // with PHP default values (e.g. status → 'brouillon', permitValidated → false)
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

        $this->checkLockedFields($existing, $originalData);
        $this->checkDateConflicts($existing, $originalData);

        $result = $this->persistProcessor->process($existing, $operation, $uriVariables, $context);

        $this->logAudit($existing, $originalData);

        $this->dispatchNotification($existing, $originalData);

        return $result;
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
                'process', 'siteCode', 'siteNumber', 'siteName', 'region',
                'theoreticalStartDate', 'expectedStartDate', 'expectedEndDate',
            ];
        }

        if ($originalPermitValidated) {
            $lockedFields = array_merge($lockedFields, [
                'provider', 'projectDescription',
            ]);
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

    private function checkDateConflicts(ActivityPlanning $planning, array $originalData): void
    {
        $datesChanged = false;
        foreach (self::DATE_FIELDS as $field) {
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
                $datesChanged = true;
                break;
            }
        }

        if ($datesChanged && $this->conflictDetector->hasConflict($planning)) {
            throw new ConflictHttpException('conflict_with_active_interventions');
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
            'process', 'provider', 'projectDescription',
            'siteCode', 'siteNumber', 'siteName', 'region',
            'theoreticalStartDate', 'expectedStartDate', 'expectedEndDate',
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
        } catch (\Throwable) {
        }
    }
}
