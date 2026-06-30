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

        if ($value->getEntrepriseName() === null) {
            return;
        }

        if (!in_array('ROLE_PRESTATAIRE', $value->getRoles(), true)) {
            $this->context->buildViolation($constraint->message)
                ->atPath('entrepriseName')
                ->addViolation();
        }
    }
}
