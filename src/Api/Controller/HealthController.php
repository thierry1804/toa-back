<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\Health\Service\HealthChecker;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoint public de supervision : 200 si toutes les dépendances répondent, 503 sinon.
 * Le détail par dépendance n'est exposé qu'en cas d'échec ; aucun message d'erreur n'est renvoyé.
 */
#[Route('/api/health', name: 'health', methods: ['GET'])]
final class HealthController
{
    public function __construct(private readonly HealthChecker $healthChecker) {}

    public function __invoke(): JsonResponse
    {
        $checks  = $this->healthChecker->check();
        $healthy = !in_array(false, $checks, true);

        $payload = $healthy
            ? ['status' => 'ok']
            : ['status' => 'degraded', 'checks' => array_map(static fn (bool $ok): string => $ok ? 'ok' : 'down', $checks)];

        $response = new JsonResponse($payload, $healthy ? JsonResponse::HTTP_OK : JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
