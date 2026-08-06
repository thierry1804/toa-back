<?php

declare(strict_types=1);

namespace App\Domain\PlanPrevention\EventListener;

use App\Domain\ActivityPlanning\Entity\ActivityPlanning;
use App\Domain\ActivityPlanning\Entity\SectionPlanifiee;
use App\Domain\ActivityPlanning\Entity\TachePlanifiee;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postLoad, method: 'postLoad', entity: PlanPrevention::class)]
final class PlanPreventionPostLoadListener
{
    public function postLoad(PlanPrevention $plan, PostLoadEventArgs $args): void
    {
        $planificationId = $plan->getPlanificationId();
        if ($planificationId === null) {
            return;
        }

        $em = $args->getObjectManager();
        $planification = $em->find(ActivityPlanning::class, $planificationId);
        if ($planification === null) {
            return;
        }

        $rawSites = $planification->getSites() ?? [];
        if (empty($rawSites) && $planification->getSiteCode() !== null) {
            $rawSites = [['codeSite' => $planification->getSiteCode(), 'nomSite' => $planification->getSiteName() ?? '']];
        }
        $plan->setPlanificationSites($rawSites);

        $sections = array_values(array_map(
            static fn(SectionPlanifiee $section): array => [
                'id'      => (string) $section->getId(),
                'libelle' => $section->getLibelle(),
                'ordre'   => $section->getOrdre(),
                'taches'  => array_values(array_map(
                    static fn(TachePlanifiee $tache): array => [
                        'id'       => (string) $tache->getId(),
                        'ordre'    => $tache->getOrdre(),
                        'tache'    => $tache->getTache(),
                        'materiel' => $tache->getMateriel(),
                        'qui'      => $tache->getQui(),
                    ],
                    $section->getTaches()->toArray(),
                )),
            ],
            $planification->getSections()->toArray(),
        ));

        $plan->setPlanificationSections($sections);
    }
}
