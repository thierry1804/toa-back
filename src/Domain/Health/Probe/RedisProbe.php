<?php

declare(strict_types=1);

namespace App\Domain\Health\Probe;

use Predis\Client;

final class RedisProbe implements HealthProbeInterface
{
    public function __construct(private readonly Client $redis) {}

    public function getName(): string
    {
        return 'redis';
    }

    public function check(): void
    {
        $this->redis->ping();
    }
}
