<?php

namespace App\Domain\User\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
class UniqueEmail extends Constraint
{
    public string $message = 'email_already_used';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
