<?php

namespace App\Domain\ActivityPlanning\Service;

use App\Domain\ActivityPlanning\Entity\ActivityPlanning;
use App\Domain\ActivityPlanning\Entity\ActivityPlanningAudit;
use Doctrine\ORM\EntityManagerInterface;

class AuditLogger
{
    private const TRACKED_FIELDS = [
        'process', 'provider', 'providerEmail', 'projectDescription',
        'siteCode', 'siteNumber', 'siteName', 'region',
        'theoreticalStartDate', 'expectedStartDate', 'expectedEndDate',
        'status', 'permitReference', 'permitValidated',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function logChanges(
        ActivityPlanning $planning,
        array $originalData,
        string $changedBy,
    ): void {
        foreach (self::TRACKED_FIELDS as $field) {
            $getter = 'get' . ucfirst($field);
            if (!method_exists($planning, $getter)) {
                continue;
            }

            $newValue = $planning->$getter();
            $oldValue = $originalData[$field] ?? null;

            $newStr = $this->normalize($newValue);
            $oldStr = $this->normalize($oldValue);

            if ($newStr !== $oldStr) {
                $audit = new ActivityPlanningAudit(
                    fieldName: $field,
                    oldValue: $oldStr,
                    newValue: $newStr,
                    changedBy: $changedBy,
                );
                $planning->addAuditLog($audit);
                $this->entityManager->persist($audit);
            }
        }
    }

    private function normalize(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('c');
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}
