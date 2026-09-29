<?php

declare(strict_types=1);

namespace App\Domain\PlanPrevention\Service;

use App\Domain\ActivityPlanning\Entity\ActivityPlanning;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Un plan de prévention découle d'une planification : à défaut de chef de projet
 * explicitement assigné, celui qui a créé la planification liée en tient lieu.
 */
class PlanPreventionChefProjetResolver
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function resolve(PlanPrevention $plan): ?User
    {
        if ($plan->getChefProjet() instanceof User) {
            return $plan->getChefProjet();
        }

        if ($plan->getPlanificationId() === null) {
            return null;
        }

        $planification = $this->entityManager->find(ActivityPlanning::class, $plan->getPlanificationId());

        return $planification?->getCreatedBy();
    }

    /**
     * Résout et affecte (sans flush) le chef de projet manquant, à partir de la planification liée.
     * Ne fait rien si un chef de projet est déjà assigné.
     */
    public function backfill(PlanPrevention $plan): ?User
    {
        if ($plan->getChefProjet() === null) {
            $resolved = $this->resolve($plan);
            if ($resolved instanceof User) {
                $plan->setChefProjet($resolved);
            }
        }

        return $plan->getChefProjet();
    }
}
