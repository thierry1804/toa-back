<?php

declare(strict_types=1);

namespace App\Domain\ActivityPlanning\Service;

use App\Domain\ActivityPlanning\Entity\ActivityPlanning;
use App\Domain\Entreprise\Entity\Entreprise;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepository;

/**
 * R-04 : le formulaire de planification ne transmet que le prestataire choisi
 * (provider = « prénom nom », providerEmail). Le champ `entreprise`, sur
 * lequel la liste côté prestataire est filtrée, restait donc toujours vide.
 * On le déduit ici du compte prestataire désigné : par email d'abord, puis
 * par nom complet quand un seul prestataire porte ce nom.
 */
class PlanningEntrepriseResolver
{
    public function __construct(private readonly UserRepository $userRepository)
    {
    }

    public function resolve(ActivityPlanning $planning): ?Entreprise
    {
        $prestataires = $this->userRepository->findByRole('ROLE_PRESTATAIRE');

        $email = mb_strtolower(trim((string) $planning->getProviderEmail()));
        if ($email !== '') {
            foreach ($prestataires as $user) {
                if (mb_strtolower((string) $user->getEmail()) === $email && $user->getEntreprise() !== null) {
                    return $user->getEntreprise();
                }
            }
        }

        $name = $this->normalize((string) $planning->getProvider());
        if ($name === '') {
            return null;
        }

        $matches = array_values(array_filter(
            $prestataires,
            fn (User $user) => $user->getEntreprise() !== null
                && $this->normalize($user->getFirstname() . ' ' . $user->getName()) === $name,
        ));

        return count($matches) === 1 ? $matches[0]->getEntreprise() : null;
    }

    /** Recalcule l'entreprise du planning ; renvoie true si elle a changé. */
    public function apply(ActivityPlanning $planning): bool
    {
        $entreprise = $this->resolve($planning);
        if ((string) $entreprise?->getId() === (string) $planning->getEntreprise()?->getId()) {
            return false;
        }

        $planning->setEntreprise($entreprise);

        return true;
    }

    private function normalize(string $value): string
    {
        return mb_strtolower((string) preg_replace('/\s+/', ' ', trim($value)));
    }
}
