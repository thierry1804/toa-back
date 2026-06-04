<?php

namespace App\Security\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationFailureResponse;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\RateLimiter\Exception\TooManyLoginAttemptsAuthenticationException;

#[AsEventListener(event: 'lexik_jwt_authentication.on_authentication_failure')]
class AuthenticationFailureListener
{
    public function __invoke(AuthenticationFailureEvent $event): void
    {
        $exception = $event->getException();
        $message = 'Identifiants incorrects';

        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            $message = 'Trop de tentatives de connexion. Veuillez réessayer plus tard.';
        }

        $response = new JWTAuthenticationFailureResponse($message, JsonResponse::HTTP_UNAUTHORIZED);
        
        $event->setResponse($response);
    }
}
