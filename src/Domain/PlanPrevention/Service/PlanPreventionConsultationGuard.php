<?php

declare(strict_types=1);

namespace App\Domain\PlanPrevention\Service;

use App\Domain\PlanPrevention\Entity\PlanPrevention;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class PlanPreventionConsultationGuard
{
    public function assertAllConsulted(PlanPrevention $plan): void
    {
        $pending = [];
        foreach ($plan->getDocuments() as $document) {
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

    public function resetConsultations(PlanPrevention $plan): void
    {
        foreach ($plan->getDocuments() as $document) {
            $document->resetConsultation();
        }
    }
}
