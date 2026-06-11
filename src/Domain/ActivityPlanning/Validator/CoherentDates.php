<?php

namespace App\Domain\ActivityPlanning\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
class CoherentDates extends Constraint
{
    public string $message = 'dates_incoherent';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
