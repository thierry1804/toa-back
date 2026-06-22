<?php

declare(strict_types=1);

namespace App\Security\EventListener;

use App\Security\LoginAttemptManager;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccountStatusException;

class LoginRateLimitListener
{
    private const LOGIN_PATH = '/api/login_check';

    public function __construct(
        private readonly LoginAttemptManager $attemptManager,
        private readonly RequestStack $requestStack,
    ) {}

    /**
     * Intercept blocked IPs before the firewall / authenticator runs.
     * Runs at priority 15, after the router (32) but before the firewall (8).
     */
    #[AsEventListener(event: KernelEvents::REQUEST, priority: 15)]
    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ($request->getPathInfo() !== self::LOGIN_PATH || $request->getMethod() !== 'POST') {
            return;
        }

        $block = $this->attemptManager->getBlock((string) $request->getClientIp());
        if ($block === null) {
            return;
        }

        $event->setResponse($this->blockedResponse($block['retry_after']));
        $event->stopPropagation();
    }

    /**
     * Record the failure after the base error response has been set (priority -10).
     * Overrides the response for warnings (4th attempt) and new blocks (5th attempt).
     * AccountStatusException failures (account disabled, etc.) are not counted.
     */
    #[AsEventListener(event: 'lexik_jwt_authentication.on_authentication_failure', priority: -10)]
    public function onAuthFailure(AuthenticationFailureEvent $event): void
    {
        if ($event->getException() instanceof AccountStatusException) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return;
        }

        $result = $this->attemptManager->recordFailure((string) $request->getClientIp());

        match ($result['action']) {
            'warn'  => $event->setResponse($this->warnResponse()),
            'block' => $event->setResponse($this->blockedResponse((int) $result['retry_after'])),
            default => null,
        };
    }

    /**
     * Clear the attempt counter when login succeeds.
     */
    #[AsEventListener(event: 'lexik_jwt_authentication.on_authentication_success')]
    public function onAuthSuccess(AuthenticationSuccessEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request !== null) {
            $this->attemptManager->onSuccess((string) $request->getClientIp());
        }
    }

    // ── Response builders ────────────────────────────────────────────────────

    private function blockedResponse(int $retryAfter): JsonResponse
    {
        $response = new JsonResponse([
            'code'        => Response::HTTP_TOO_MANY_REQUESTS,
            'message'     => 'too_many_login_attempts',
            'retry_after' => $retryAfter,
        ], Response::HTTP_TOO_MANY_REQUESTS);

        $response->headers->set('Retry-After', (string) $retryAfter);

        return $response;
    }

    private function warnResponse(): JsonResponse
    {
        return new JsonResponse([
            'code'    => Response::HTTP_UNAUTHORIZED,
            'message' => 'login_attempt_warning',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
