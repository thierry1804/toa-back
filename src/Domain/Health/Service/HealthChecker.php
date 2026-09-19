<?php

declare(strict_types=1);

namespace App\Domain\Health\Service;

use App\Domain\Health\Probe\HealthProbeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class HealthChecker
{
    /**
     * @param iterable<HealthProbeInterface> $probes
     */
    public function __construct(
        #[AutowireIterator('app.health_probe')]
        private readonly iterable $probes,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return array<string, bool> état de chaque dépendance (true = joignable)
     */
    public function check(): array
    {
        $results = [];

        foreach ($this->probes as $probe) {
            try {
                $probe->check();
                $results[$probe->getName()] = true;
            } catch (\Throwable $e) {
                $this->logger->error('health_check_failed', ['service' => $probe->getName(), 'exception' => $e]);
                $results[$probe->getName()] = false;
            }
        }

        return $results;
    }
}
