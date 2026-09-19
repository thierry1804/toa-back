<?php

declare(strict_types=1);

namespace App\Domain\Health\Probe;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GotenbergProbe implements HealthProbeInterface
{
    private const TIMEOUT_SECONDS = 3;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $gotenbergUrl,
    ) {}

    public function getName(): string
    {
        return 'gotenberg';
    }

    public function check(): void
    {
        // getContent() lève une exception pour toute réponse hors 2xx.
        $this->httpClient
            ->request('GET', rtrim($this->gotenbergUrl, '/') . '/health', ['timeout' => self::TIMEOUT_SECONDS, 'max_duration' => self::TIMEOUT_SECONDS])
            ->getContent();
    }
}
