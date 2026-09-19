<?php

declare(strict_types=1);

namespace App\Domain\ActivityPlanning\Service;

use App\Domain\ActivityPlanning\Entity\ActivityPlanning;
use App\Domain\ActivityPlanning\Entity\SectionPlanifiee;
use App\Domain\ActivityPlanning\Entity\TachePlanifiee;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Planifications qu'un prestataire peut rattacher à un nouveau plan de prévention.
 * Partagé par GET /api/planifications et par le snapshot hors-ligne.
 */
class PlanificationsDisponiblesProvider
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** @return list<array<string, mixed>> */
    public function forUser(User $user): array
    {
        $planifications = $this->em
            ->getRepository(ActivityPlanning::class)
            ->createQueryBuilder('ap')
            ->leftJoin('ap.sections', 's')
            ->leftJoin('s.taches', 't')
            ->addSelect('s', 't')
            ->where(
                'ap.providerEmail = :email OR (ap.providerEmail IS NULL AND ap.provider = :entrepriseName)'
            )
            ->andWhere('ap.status IN (:statuses)')
            ->setParameter('email', $user->getEmail())
            ->setParameter('entrepriseName', $user->getEntrepriseName() ?? '')
            ->setParameter('statuses', [
                ActivityPlanning::STATUS_PLANIFIE,
                ActivityPlanning::STATUS_EN_COURS,
                ActivityPlanning::STATUS_VALIDE,
            ])
            ->orderBy('ap.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $usedIds = array_map(
            static fn(array $row): int => (int) $row['planificationId'],
            $this->em->getRepository(PlanPrevention::class)
                ->createQueryBuilder('pp')
                ->select('pp.planificationId')
                ->where('pp.planificationId IS NOT NULL')
                ->getQuery()
                ->getScalarResult(),
        );

        $member = array_map(
            static fn(ActivityPlanning $p): array => [
                '@id'                  => '/api/activity_plannings/' . $p->getId(),
                'disponible'           => !in_array($p->getId(), $usedIds, true),
                'id'                   => $p->getId(),
                'process'              => $p->getProcess(),
                'provider'             => $p->getProvider(),
                'providerEmail'        => $p->getProviderEmail(),
                'siteCode'             => $p->getSiteCode(),
                'siteName'             => $p->getSiteName(),
                'typeIntervention'     => $p->getTypeIntervention(),
                'theoreticalStartDate' => $p->getTheoreticalStartDate()?->format(\DateTimeInterface::ATOM),
                'expectedStartDate'    => $p->getExpectedStartDate()?->format(\DateTimeInterface::ATOM),
                'expectedEndDate'      => $p->getExpectedEndDate()?->format(\DateTimeInterface::ATOM),
                'actualStartDate'      => $p->getActualStartDate()?->format(\DateTimeInterface::ATOM),
                'actualEndDate'        => $p->getActualEndDate()?->format(\DateTimeInterface::ATOM),
                'status'               => $p->getStatus(),
                'permitReference'      => $p->getPermitReference(),
                'permitValidated'      => $p->isPermitValidated(),
                'sites'                => $p->getSites() ?? [],
                'sections'             => array_values(array_map(
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
                    $p->getSections()->toArray(),
                )),
                'createdAt'            => $p->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                'updatedAt'            => $p->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
                'createdBy'            => $p->getCreatedBy() !== null ? [
                    '@id'       => '/api/users/' . $p->getCreatedBy()->getId(),
                    'id'        => $p->getCreatedBy()->getId(),
                    'email'     => $p->getCreatedBy()->getEmail(),
                    'name'      => $p->getCreatedBy()->getName(),
                    'firstname' => $p->getCreatedBy()->getFirstname(),
                ] : null,
            ],
            $planifications,
        );

        return $member;
    }
}
