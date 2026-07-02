<?php

namespace App\Domain\User\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
class EntrepriseNameCoherence extends Constraint
{
    public string $message = 'entreprise_name_not_allowed_for_role';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
