<?php

namespace App\Domain\User\Validator;

use App\Domain\User\Entity\User;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class EntrepriseNameCoherenceValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof EntrepriseNameCoherence) {
            throw new UnexpectedTypeException($constraint, EntrepriseNameCoherence::class);
        }

        if (!$value instanceof User) {
            throw new UnexpectedValueException($value, User::class);
        }

        $isPrestataire   = in_array('ROLE_PRESTATAIRE', $value->getRoles(), true);
        $isHse           = in_array('ROLE_HSE', $value->getRoles(), true);
        $isAgentTerrain  = in_array('ROLE_AGENT_TERRAIN', $value->getRoles(), true);
        // R-01 : le chef de projet prestataire appartient lui aussi à l'équipe
        // d'une entreprise prestataire.
        $isChefProjetPrestataire = in_array('ROLE_CHEF_PROJET_PRESTATAIRE', $value->getRoles(), true);
        $canHaveEntreprise = $isPrestataire || $isHse || $isAgentTerrain || $isChefProjetPrestataire;

        if ($value->getEntrepriseName() !== null && !$canHaveEntreprise) {
            $this->context->buildViolation($constraint->message)
                ->atPath('entrepriseName')
                ->addViolation();
        }

        if ($value->getNumeroRegistreCommerce() !== null && !$canHaveEntreprise) {
            $this->context->buildViolation($constraint->message)
                ->atPath('numeroRegistreCommerce')
                ->addViolation();
        }

        if ($value->getSiegeSocial() !== null && !$canHaveEntreprise) {
            $this->context->buildViolation($constraint->message)
                ->atPath('siegeSocial')
                ->addViolation();
        }
    }
}
