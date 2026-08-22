<?php

namespace App\Domain\Entreprise\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
class UniqueEntrepriseNom extends Constraint
{
    public string $message = 'entreprise_nom_deja_existant';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
