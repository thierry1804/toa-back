<?php

namespace App\Domain\User\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
class UniqueEmail extends Constraint
{
    public string $message = 'Email déjà utilisé';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
