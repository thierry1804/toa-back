<?php

declare(strict_types=1);

namespace App\Domain\Intervention\Enum;

enum NiveauCouleurRisque: string
{
    case VERT   = 'VERT';
    case JAUNE  = 'JAUNE';
    case ROUGE  = 'ROUGE';

    public static function fromNiveau(int $niveau): self
    {
        return match (true) {
            $niveau >= 15 => self::ROUGE,
            $niveau >= 8  => self::JAUNE,
            default       => self::VERT,
        };
    }
}
