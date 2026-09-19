<?php

declare(strict_types=1);

namespace App\Domain\Health\Probe;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.health_probe')]
interface HealthProbeInterface
{
    public function getName(): string;

    /**
     * @throws \Throwable si la dépendance est injoignable ou en erreur
     */
    public function check(): void;
}
