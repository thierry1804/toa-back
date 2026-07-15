<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\ActivityPlanning\Entity\ActivityPlanning;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/planifications', name: 'planifications_disponibles', methods: ['GET'])]
#[IsGranted('ROLE_PRESTATAIRE')]
class PlanificationsDisponiblesController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function __invoke(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $planifications = $this->em
            ->getRepository(ActivityPlanning::class)
            ->createQueryBuilder('ap')
            ->where('ap.createdBy = :user')
            ->setParameter('user', $user)
            ->orderBy('ap.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $member = array_map(
            static fn(ActivityPlanning $p): array => [
                '@id'                  => '/api/activity_plannings/' . $p->getId(),
                'id'                   => $p->getId(),
                'process'              => $p->getProcess(),
                'provider'             => $p->getProvider(),
                'providerEmail'        => $p->getProviderEmail(),
                'projectDescription'   => $p->getProjectDescription(),
                'siteCode'             => $p->getSiteCode(),
                'siteNumber'           => $p->getSiteNumber(),
                'siteName'             => $p->getSiteName(),
                'region'               => $p->getRegion(),
                'theoreticalStartDate' => $p->getTheoreticalStartDate()?->format(\DateTimeInterface::ATOM),
                'expectedStartDate'    => $p->getExpectedStartDate()?->format(\DateTimeInterface::ATOM),
                'expectedEndDate'      => $p->getExpectedEndDate()?->format(\DateTimeInterface::ATOM),
                'actualStartDate'      => $p->getActualStartDate()?->format(\DateTimeInterface::ATOM),
                'actualEndDate'        => $p->getActualEndDate()?->format(\DateTimeInterface::ATOM),
                'status'               => $p->getStatus(),
                'permitReference'      => $p->getPermitReference(),
                'permitValidated'      => $p->isPermitValidated(),
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

        return $this->json([
            'member'     => $member,
            'totalItems' => count($member),
        ]);
    }
}
