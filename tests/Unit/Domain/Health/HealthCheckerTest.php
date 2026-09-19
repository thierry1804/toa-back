<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Health;

use App\Api\Controller\HealthController;
use App\Domain\Health\Probe\HealthProbeInterface;
use App\Domain\Health\Service\HealthChecker;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class HealthCheckerTest extends TestCase
{
    private function probe(string $name, bool $healthy): HealthProbeInterface
    {
        return new class ($name, $healthy) implements HealthProbeInterface {
            public function __construct(private readonly string $name, private readonly bool $healthy) {}

            public function getName(): string
            {
                return $this->name;
            }

            public function check(): void
            {
                if (!$this->healthy) {
                    throw new \RuntimeException('secret internal detail');
                }
            }
        };
    }

    public function testReportsEachProbeStatus(): void
    {
        $checker = new HealthChecker([$this->probe('database', true), $this->probe('redis', false)], new NullLogger());

        $this->assertSame(['database' => true, 'redis' => false], $checker->check());
    }

    public function testControllerReturns200WhenAllProbesAreHealthy(): void
    {
        $controller = new HealthController(new HealthChecker([$this->probe('database', true), $this->probe('redis', true)], new NullLogger()));

        $response = $controller();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"status":"ok"}', $response->getContent());
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function testControllerReturns503WithoutLeakingErrorDetailsWhenAProbeFails(): void
    {
        $controller = new HealthController(new HealthChecker([$this->probe('database', true), $this->probe('minio', false)], new NullLogger()));

        $response = $controller();

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame(
            ['status' => 'degraded', 'checks' => ['database' => 'ok', 'minio' => 'down']],
            json_decode((string) $response->getContent(), true),
        );
        $this->assertStringNotContainsString('secret internal detail', (string) $response->getContent());
    }
}
