<?php

declare(strict_types=1);

namespace App\Domain\Health\Probe;

use Doctrine\DBAL\Connection;

final class DatabaseProbe implements HealthProbeInterface
{
    public function __construct(private readonly Connection $connection) {}

    public function getName(): string
    {
        return 'database';
    }

    public function check(): void
    {
        $this->connection->executeQuery('SELECT 1');
    }
}
