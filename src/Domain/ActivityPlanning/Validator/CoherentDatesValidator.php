<?php

namespace App\Domain\ActivityPlanning\Validator;

use App\Domain\ActivityPlanning\Entity\ActivityPlanning;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class CoherentDatesValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof CoherentDates || !$value instanceof ActivityPlanning) {
            return;
        }

        $theoreticalStartDate = $value->getTheoreticalStartDate();
        $expectedStartDate = $value->getExpectedStartDate();
        $expectedEndDate = $value->getExpectedEndDate();

        if (null === $theoreticalStartDate || null === $expectedStartDate || null === $expectedEndDate) {
            return;
        }

        if ($expectedStartDate < $theoreticalStartDate) {
            $this->context->buildViolation($constraint->message)
                ->atPath('expectedStartDate')
                ->addViolation();
        }

        if ($expectedEndDate < $expectedStartDate) {
            $this->context->buildViolation($constraint->message)
                ->atPath('expectedEndDate')
                ->addViolation();
        }
    }
}
