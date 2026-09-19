<?php

declare(strict_types=1);

namespace App\Domain\PlanPrevention\Service;

use App\Domain\PlanPrevention\Entity\ModeOperatoirePlanPrevention;
use App\Domain\PlanPrevention\Entity\PhasePlanPrevention;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Entity\RisquePrevention;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Aligne les phases / modes opératoires d'un plan sur le tableau `sections`
 * reçu du client (état cible complet). Les identifiants existants sont
 * conservés pour ne pas invalider les risques rattachés à un mode opératoire.
 */
class PlanPreventionSectionsSynchronizer
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function sync(PlanPrevention $plan, mixed $rawSections): void
    {
        if (!is_array($rawSections) || !array_is_list($rawSections)) {
            throw new UnprocessableEntityHttpException('sections_invalid');
        }

        /** @var array<string, PhasePlanPrevention> $existingPhases */
        $existingPhases = [];
        /** @var array<string, ModeOperatoirePlanPrevention> $existingModes */
        $existingModes = [];
        foreach ($plan->getSections() as $phase) {
            $existingPhases[(string) $phase->getId()] = $phase;
            foreach ($phase->getModesOperatoires() as $mode) {
                $existingModes[(string) $mode->getId()] = $mode;
            }
        }

        $keptPhaseIds = [];
        $keptModeIds = [];

        foreach ($rawSections as $index => $rawPhase) {
            if (!is_array($rawPhase)) {
                throw new UnprocessableEntityHttpException('sections_invalid');
            }

            $phaseId = $this->optionalId($rawPhase);
            if ($phaseId !== null) {
                $phase = $existingPhases[$phaseId] ?? throw new UnprocessableEntityHttpException('section_introuvable');
                $keptPhaseIds[$phaseId] = true;
            } else {
                $phase = new PhasePlanPrevention();
                $plan->addSection($phase);
            }

            $phase->setLibelle($this->requiredString($rawPhase, 'libelle', 150));
            $phase->setOrdre($this->ordre($rawPhase, (int) $index));

            $rawModes = $rawPhase['taches'] ?? [];
            if (!is_array($rawModes) || !array_is_list($rawModes)) {
                throw new UnprocessableEntityHttpException('sections_invalid');
            }

            foreach ($rawModes as $modeIndex => $rawMode) {
                if (!is_array($rawMode)) {
                    throw new UnprocessableEntityHttpException('sections_invalid');
                }

                $moved = false;
                $modeId = $this->optionalId($rawMode);
                if ($modeId !== null) {
                    $mode = $existingModes[$modeId] ?? throw new UnprocessableEntityHttpException('tache_planifiee_introuvable');
                    $keptModeIds[$modeId] = true;
                    $previousPhase = $mode->getPhase();
                    if ($previousPhase !== null && $previousPhase !== $phase) {
                        $previousPhase->removeModeOperatoire($mode);
                        $moved = true;
                    }
                } else {
                    $mode = new ModeOperatoirePlanPrevention();
                }

                $phase->addModeOperatoire($mode);
                $mode->setPhase($phase);
                if ($moved) {
                    // Une phase neuve porte une ArrayCollection simple : seul Doctrine sait annuler
                    // l'orphan removal planifié par le retrait de l'ancienne phase.
                    $this->entityManager->getUnitOfWork()->cancelOrphanRemoval($mode);
                }
                $mode->setTache($this->requiredString($rawMode, 'tache', 255));
                $mode->setMateriel($this->optionalString($rawMode, 'materiel', 255));
                $mode->setQui($this->optionalString($rawMode, 'qui', 150));
                $mode->setOrdre($this->ordre($rawMode, (int) $modeIndex));
            }
        }

        foreach ($existingPhases as $id => $phase) {
            if (!isset($keptPhaseIds[$id])) {
                $plan->removeSection($phase);
            }
        }

        foreach ($existingModes as $id => $mode) {
            if (!isset($keptModeIds[$id])) {
                $mode->getPhase()?->removeModeOperatoire($mode);
                $this->detachRisques($plan, $id);
            }
        }
    }

    private function detachRisques(PlanPrevention $plan, string $modeId): void
    {
        foreach ($plan->getRisques() as $risque) {
            if ($risque instanceof RisquePrevention && $risque->getTachePlanifieeId() === $modeId) {
                $risque->setTachePlanifieeId(null);
            }
        }
    }

    /** @param array<mixed> $raw */
    private function optionalId(array $raw): ?string
    {
        $id = $raw['id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    /** @param array<mixed> $raw */
    private function requiredString(array $raw, string $key, int $maxLength): string
    {
        $value = $raw[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new UnprocessableEntityHttpException(sprintf('%s_required', $key));
        }

        $value = trim($value);
        if (mb_strlen($value) > $maxLength) {
            throw new UnprocessableEntityHttpException(sprintf('%s_too_long', $key));
        }

        return $value;
    }

    /** @param array<mixed> $raw */
    private function optionalString(array $raw, string $key, int $maxLength): ?string
    {
        $value = $raw[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value) || mb_strlen(trim($value)) > $maxLength) {
            throw new UnprocessableEntityHttpException(sprintf('%s_invalid', $key));
        }

        return trim($value);
    }

    /** @param array<mixed> $raw */
    private function ordre(array $raw, int $default): int
    {
        $ordre = $raw['ordre'] ?? $default;
        if (!is_int($ordre) || $ordre < 0) {
            throw new UnprocessableEntityHttpException('ordre_invalid');
        }

        return $ordre;
    }
}
