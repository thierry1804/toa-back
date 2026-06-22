<?php

declare(strict_types=1);

namespace App\Security\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class AccessDeniedListener
{
    /**
     * Runs before API Platform handles the exception.
     * Stores the translation key from the AccessDeniedException message in the request
     * so the response listener can append it to the JSON body.
     */
    #[AsEventListener(event: KernelEvents::EXCEPTION, priority: 100)]
    public function onException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (!$exception instanceof AccessDeniedException) {
            return;
        }

        $message = $exception->getMessage();
        $errorKey = preg_match('/^[a-z][a-z0-9_.]*$/', $message) ? $message : 'error.voter.access_denied';

        $event->getRequest()->attributes->set('_error_key', $errorKey);
    }

    /**
     * Appends error_key to every 403 JSON response that was produced from a voter denial.
     */
    #[AsEventListener(event: KernelEvents::RESPONSE, priority: -100)]
    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();

        if ($response->getStatusCode() !== 403) {
            return;
        }

        $errorKey = $event->getRequest()->attributes->get('_error_key');
        if (null === $errorKey) {
            return;
        }

        if (!str_contains($response->headers->get('Content-Type', ''), 'json')) {
            return;
        }

        $content = json_decode($response->getContent(), true);
        if (!is_array($content)) {
            return;
        }

        $content['error_key'] = $errorKey;
        $response->setContent(json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
