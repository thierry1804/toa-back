<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * A prestataire scoped to a single entreprise (ROLE_PRESTATAIRE without
 * ROLE_ADMIN/ROLE_SUPER_ADMIN) is allowed to create/edit users via the API,
 * but UserVoter only checks *whether* the action is allowed — not whether
 * the submitted `roles`/`entreprise` payload stays within that prestataire's
 * own scope. Left unchecked, a prestataire can POST a new user with
 * roles: ["ROLE_SUPER_ADMIN"] or an unrelated entreprise IRI (confirmed via
 * live testing). This processor forces those fields server-side, ignoring
 * whatever the payload contains, before the write reaches the database.
 */
final class PrestataireUserScopeProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: UserPasswordHasherProcessor::class)]
        private readonly ProcessorInterface $inner,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof User) {
            $this->enforceScope($data, $operation, $uriVariables);
        }

        return $this->inner->process($data, $operation, $uriVariables, $context);
    }

    private function enforceScope(User $data, Operation $operation, array $uriVariables): void
    {
        $actor = $this->tokenStorage->getToken()?->getUser();
        if (!$actor instanceof User || !$this->isPrestataireScoped($actor)) {
            return;
        }

        if ($operation instanceof Post) {
            // Blocked by UserVoter when null, but guard defensively anyway.
            if ($actor->getEntreprise() === null) {
                return;
            }

            $data->setRoles(['ROLE_PRESTATAIRE']);
            $data->setEntreprise($actor->getEntreprise());

            return;
        }

        // Put/Patch
        $targetId = $data->getId() ?? (isset($uriVariables['id']) ? (int) $uriVariables['id'] : null);
        $isSelf   = $targetId !== null && $targetId === $actor->getId();

        if ($isSelf) {
            // Never let a prestataire escalate their own role.
            $data->setRoles(['ROLE_PRESTATAIRE']);

            $actorEntreprise = $actor->getEntreprise();
            if ($actorEntreprise !== null) {
                // Already linked — switching to a different entreprise via
                // self-edit is not a supported flow, keep the current one.
                $data->setEntreprise($actorEntreprise);

                return;
            }

            // First-time link: only allow it if no one else already claims
            // that entreprise, otherwise a prestataire could just PATCH
            // themselves onto an existing rival company.
            $target = $data->getEntreprise();
            if ($target !== null && $this->isEntrepriseAlreadyClaimed($target->getId(), $actor)) {
                $data->setEntreprise(null);
            }

            return;
        }

        // Editing a teammate: UserVoter already confirmed they share the
        // actor's entreprise before we get here — keep them pinned there
        // and prevent role escalation.
        $data->setRoles(['ROLE_PRESTATAIRE']);
        if ($actor->getEntreprise() !== null) {
            $data->setEntreprise($actor->getEntreprise());
        }
    }

    private function isEntrepriseAlreadyClaimed(mixed $entrepriseId, User $actor): bool
    {
        if ($entrepriseId === null) {
            return false;
        }

        foreach ($this->userRepository->findBy(['entreprise' => $entrepriseId]) as $existing) {
            if ($existing->getId() !== $actor->getId()) {
                return true;
            }
        }

        return false;
    }

    private function isPrestataireScoped(User $user): bool
    {
        $roles = $user->getRoles();

        return in_array('ROLE_PRESTATAIRE', $roles, true)
            && !in_array('ROLE_ADMIN', $roles, true)
            && !in_array('ROLE_SUPER_ADMIN', $roles, true);
    }
}
