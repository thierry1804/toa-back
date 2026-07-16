<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * TEMPORARY — delete this file after use.
 */
class TempMigrateController
{
    private const SECRET = 'toa-migrate-2026';

    public function __construct(private readonly Connection $connection) {}

    #[Route('/internal/temp-migrate', name: 'temp_migrate', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        if ($request->headers->get('X-Migrate-Secret') !== self::SECRET) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $results = [];

        $statements = [
            'ALTER TABLE "user" ADD COLUMN IF NOT EXISTS numero_registre_commerce VARCHAR(255) DEFAULT NULL',
            'ALTER TABLE "user" ADD COLUMN IF NOT EXISTS siege_social             VARCHAR(255) DEFAULT NULL',
            'ALTER TABLE "user" ADD COLUMN IF NOT EXISTS qualite_representant     VARCHAR(100) DEFAULT NULL',
            'ALTER TABLE "user" ADD COLUMN IF NOT EXISTS signature_path           VARCHAR(255) DEFAULT NULL',
        ];

        foreach ($statements as $sql) {
            try {
                $this->connection->executeStatement($sql);
                $results[] = ['sql' => $sql, 'status' => 'ok'];
            } catch (\Throwable $e) {
                $results[] = ['sql' => $sql, 'status' => 'error', 'message' => $e->getMessage()];
            }
        }

        return new JsonResponse(['results' => $results]);
    }
}
