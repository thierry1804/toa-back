<?php

declare(strict_types=1);

namespace App\Domain\Health\Probe;

use Aws\S3\S3Client;

final class MinioProbe implements HealthProbeInterface
{
    private const TIMEOUT_SECONDS = 3;

    public function __construct(
        private readonly S3Client $s3Client,
        private readonly string $bucket,
    ) {}

    public function getName(): string
    {
        return 'minio';
    }

    public function check(): void
    {
        // Vérifie à la fois la joignabilité, les identifiants et l'existence du bucket.
        $this->s3Client->headBucket([
            'Bucket' => $this->bucket,
            '@http'  => ['connect_timeout' => self::TIMEOUT_SECONDS, 'timeout' => self::TIMEOUT_SECONDS],
        ]);
    }
}
