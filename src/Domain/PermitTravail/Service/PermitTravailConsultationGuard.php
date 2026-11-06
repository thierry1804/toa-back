<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Service;

use App\Domain\PermitTravail\Entity\PermitTravail;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class PermitTravailConsultationGuard
{
    public function assertAllConsulted(PermitTravail $permit): void
    {
        $pending = [];
        foreach ($permit->getDocuments() as $document) {
            if (!$document->isNonApplicable() && $document->getConsultedAt() === null) {
                $pending[$document->getType()?->value ?? 'DOCUMENT'] = true;
            }
        }

        if ($pending !== []) {
            throw new UnprocessableEntityHttpException(
                sprintf('documents_non_consultes: %s', implode(', ', array_keys($pending))),
            );
        }
    }

    public function resetConsultations(PermitTravail $permit): void
    {
        foreach ($permit->getDocuments() as $document) {
            $document->resetConsultation();
        }
    }
}
