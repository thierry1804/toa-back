<?php

namespace App\Security\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationFailureResponse;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Exception\AccountStatusException;

// Runs at default priority (0) — LoginRateLimitListener at priority -10 may override
// the response for warnings and rate-limit blocks after this runs.
#[AsEventListener(event: 'lexik_jwt_authentication.on_authentication_failure')]
class AuthenticationFailureListener
{
    public function __invoke(AuthenticationFailureEvent $event): void
    {
        $exception = $event->getException();

        $message = $exception instanceof AccountStatusException
            ? $exception->getMessageKey()
            : 'incorrect_credentials';

        $event->setResponse(
            new JWTAuthenticationFailureResponse($message, JsonResponse::HTTP_UNAUTHORIZED)
        );
    }
}
