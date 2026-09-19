<?php

declare(strict_types=1);

namespace App\Tests\Unit\Api\Support;

use App\Api\Support\UploadRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class UploadRulesTest extends TestCase
{
    private static function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-09-15 10:00:00', new \DateTimeZone('UTC'));
    }

    public function testFallsBackToServerTimeWhenCapturedAtIsMissing(): void
    {
        $now = new \DateTimeImmutable('2026-09-15 10:00:00');

        $this->assertEquals($now, UploadRules::capturedAt(new Request(), $now));
    }

    public function testConvertsProvidedCapturedAtToServerTimezone(): void
    {
        $request = new Request(request: ['capturedAt' => '2026-09-15T10:00:00+03:00']);

        $result = UploadRules::capturedAt($request, self::now());

        $this->assertSame('2026-09-15 07:00:00', $result->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $result->getTimezone()->getName());
    }

    public function testAcceptsJavascriptIsoStringWithMillisecondsAndZulu(): void
    {
        $request = new Request(request: ['capturedAt' => '2026-09-15T09:30:00.123Z']);

        $result = UploadRules::capturedAt($request, self::now());

        $this->assertSame('2026-09-15 09:30:00', $result->format('Y-m-d H:i:s'));
    }

    public function testRejectsFutureCapturedAt(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('captured_at_in_future');

        UploadRules::capturedAt(new Request(request: ['capturedAt' => '2026-09-16T10:00:00+00:00']), self::now());
    }

    public function testRejectsCapturedAtOlderThanThirtyDays(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('captured_at_too_old');

        UploadRules::capturedAt(new Request(request: ['capturedAt' => '2026-08-01T10:00:00+00:00']), self::now());
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidValues(): iterable
    {
        yield 'texte libre' => ['pas une date'];
        yield 'yesterday' => ['yesterday'];
        yield 'timestamp @0' => ['@0'];
        yield 'sans fuseau' => ['2026-09-15T10:00:00'];
        yield 'date seule' => ['2026-09-15'];
        yield 'date inexistante' => ['2026-02-31T10:00:00+00:00'];
        yield 'tableau' => [['2026-09-15T10:00:00+00:00']];
    }

    #[DataProvider('invalidValues')]
    public function testRejectsNonIso8601Values(mixed $value): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('captured_at_invalid');

        UploadRules::capturedAt(new Request(request: ['capturedAt' => $value]), self::now());
    }

    public function testPhotoFormatsAreAllowed(): void
    {
        foreach (['image/jpeg', 'image/png', 'image/webp', 'application/pdf'] as $mime) {
            $this->assertContains($mime, UploadRules::ALLOWED_MIME_TYPES);
        }
    }
}
