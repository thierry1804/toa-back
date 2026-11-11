<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\ActivityPlanning\Entity\ActivityPlanning;
use App\Domain\Entreprise\Entity\SousTraitant;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class PlanificationAffectationController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route('/api/planifications-affectations', name: 'planifications_affectations_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $user = $this->requireManager();
        $plans = $this->entityManager->getRepository(ActivityPlanning::class)->findBy(
            ['entreprise' => $user->getEntreprise()],
            ['id' => 'DESC'],
        );

        return $this->json([
            'planifications' => array_map(fn (ActivityPlanning $plan): array => $this->serialize($plan), $plans),
        ]);
    }

    #[Route('/api/planifications-affectations/{id}', name: 'planifications_affectations_replace', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function replace(int $id, Request $request): JsonResponse
    {
        $user = $this->requireManager();
        $plan = $this->entityManager->find(ActivityPlanning::class, $id);
        if (!$plan instanceof ActivityPlanning) {
            throw new NotFoundHttpException('activity_planning.not_found');
        }
        if ((string) $plan->getEntreprise()?->getId() !== (string) $user->getEntreprise()?->getId()) {
            throw new AccessDeniedHttpException('error.voter.access_denied');
        }

        $body = json_decode($request->getContent(), true);
        $ids = is_array($body) ? ($body['sousTraitantIds'] ?? null) : null;
        if (!is_array($ids)) {
            throw new UnprocessableEntityHttpException('sous_traitant.ids_required');
        }

        $plan->getSousTraitants()->clear();
        foreach ($ids as $rawId) {
            $sousTraitant = $this->entityManager->find(SousTraitant::class, (string) $rawId);
            if (!$sousTraitant instanceof SousTraitant
                || (string) $sousTraitant->getEntreprise()?->getId() !== (string) $user->getEntreprise()?->getId()
            ) {
                throw new UnprocessableEntityHttpException('sous_traitant.not_in_entreprise');
            }
            $plan->addSousTraitant($sousTraitant);
        }

        $this->entityManager->flush();

        return $this->json($this->serialize($plan));
    }

    private function requireManager(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getEntreprise() === null) {
            throw new AccessDeniedHttpException('error.voter.access_denied');
        }
        $roles = $user->getRoles();
        $allowed = in_array('ROLE_PRESTATAIRE', $roles, true)
            || in_array('ROLE_CHEF_PROJET_PRESTATAIRE', $roles, true)
            || in_array('ROLE_ADMIN', $roles, true)
            || in_array('ROLE_SUPER_ADMIN', $roles, true);
        if (!$allowed) {
            throw new AccessDeniedHttpException('error.voter.access_denied');
        }

        return $user;
    }

    /** @return array{id: int, siteCode: ?string, siteName: ?string, process: ?string, typeIntervention: ?string, sousTraitantIds: list<string>, planPreventionReference: ?string, activitePlanifiee: ?string} */
    private function serialize(ActivityPlanning $plan): array
    {
        /** @var PlanPrevention[] $planPreventions */
        $planPreventions = $this->entityManager->getRepository(PlanPrevention::class)->findBy(
            ['planificationId' => $plan->getId()],
            ['createdAt' => 'DESC'],
            1,
        );
        $planPrevention = $planPreventions[0] ?? null;

        return [
            'id' => (int) $plan->getId(),
            'siteCode' => $plan->getSiteCode(),
            'siteName' => $plan->getSiteName(),
            'process' => $plan->getProcess(),
            'typeIntervention' => $plan->getTypeIntervention(),
            'sousTraitantIds' => array_values(array_map(
                static fn (SousTraitant $sousTraitant): string => (string) $sousTraitant->getId(),
                $plan->getSousTraitants()->toArray(),
            )),
            'planPreventionReference' => $planPrevention?->getReference(),
            'activitePlanifiee' => $planPrevention?->getActivitePlanifiee(),
        ];
    }
}
