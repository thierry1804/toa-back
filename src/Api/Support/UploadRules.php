<?php

declare(strict_types=1);

namespace App\Api\Support;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class UploadRules
{
    public const ALLOWED_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    private const ISO_8601 = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{1,6})?(Z|[+-]\d{2}:\d{2})$/';

    public static function capturedAt(Request $request, ?\DateTimeImmutable $now = null): \DateTimeImmutable
    {
        $now ??= new \DateTimeImmutable();
        $raw = $request->request->all()['capturedAt'] ?? null;

        if ($raw === null || $raw === '') {
            return $now;
        }

        if (!is_string($raw) || preg_match(self::ISO_8601, $raw, $matches) !== 1) {
            throw new UnprocessableEntityHttpException('captured_at_invalid');
        }

        $format = ($matches[1] ?? '') !== '' ? 'Y-m-d\TH:i:s.uP' : 'Y-m-d\TH:i:sP';
        $capturedAt = \DateTimeImmutable::createFromFormat($format, $raw);
        $errors = \DateTimeImmutable::getLastErrors();
        if ($capturedAt === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new UnprocessableEntityHttpException('captured_at_invalid');
        }

        if ($capturedAt > $now->modify('+5 minutes')) {
            throw new UnprocessableEntityHttpException('captured_at_in_future');
        }

        if ($capturedAt < $now->modify('-30 days')) {
            throw new UnprocessableEntityHttpException('captured_at_too_old');
        }

        // Doctrine persiste l'heure murale sans fuseau : on normalise sur le fuseau serveur.
        return $capturedAt->setTimezone($now->getTimezone());
    }
}
