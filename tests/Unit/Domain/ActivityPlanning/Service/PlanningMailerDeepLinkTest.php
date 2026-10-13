<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ActivityPlanning\Service;

use App\Domain\ActivityPlanning\Service\PlanningMailer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Couvre R-05 : les e-mails de planification doivent contenir un lien direct
 * vers la fiche ({FRONTEND_URL}/planning/{id}), dans le corps texte (URL en
 * clair) et dans le corps HTML (balise <a href="...">), en réutilisant le
 * paramètre FRONTEND_URL déjà introduit pour PlanPrevention/PermitTravail
 * (R-28).
 */
final class PlanningMailerDeepLinkTest extends TestCase
{
    private MailerInterface&MockObject $mailer;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
    }

    public function testCreationNotificationIncludesPlanningLinkInTextAndHtml(): void
    {
        $mailer = $this->mailer('https://toa-front.dev.arkeup.com');

        /** @var Email|null $sent */
        $sent = null;
        $this->mailer->expects($this->once())->method('send')->willReturnCallback(
            static function (Email $message) use (&$sent): void {
                $sent = $message;
            },
        );

        $mailer->sendCreationNotification(
            'prestataire@example.test',
            'Jean Dupont',
            42,
            'Maintenance',
            'SITE-1',
            'Site 1',
        );

        $expectedUrl = 'https://toa-front.dev.arkeup.com/planning/42';
        $this->assertNotNull($sent);
        $this->assertStringContainsString($expectedUrl, (string) $sent->getTextBody());
        $this->assertStringContainsString('href="' . $expectedUrl . '"', (string) $sent->getHtmlBody());
    }

    public function testModificationNotificationIncludesPlanningLinkInTextAndHtml(): void
    {
        $mailer = $this->mailer('https://toa-front.dev.arkeup.com');

        /** @var Email|null $sent */
        $sent = null;
        $this->mailer->expects($this->once())->method('send')->willReturnCallback(
            static function (Email $message) use (&$sent): void {
                $sent = $message;
            },
        );

        $mailer->sendModificationNotification(
            'prestataire@example.test',
            'Jean Dupont',
            42,
            'Maintenance',
            ['status' => ['old' => 'planifie', 'new' => 'en_cours']],
        );

        $expectedUrl = 'https://toa-front.dev.arkeup.com/planning/42';
        $this->assertNotNull($sent);
        $this->assertStringContainsString($expectedUrl, (string) $sent->getTextBody());
        $this->assertStringContainsString('href="' . $expectedUrl . '"', (string) $sent->getHtmlBody());
    }

    public function testEmptyFrontendUrlYieldsNoLink(): void
    {
        $mailer = $this->mailer('');

        /** @var Email|null $sent */
        $sent = null;
        $this->mailer->expects($this->once())->method('send')->willReturnCallback(
            static function (Email $message) use (&$sent): void {
                $sent = $message;
            },
        );

        $mailer->sendCreationNotification(
            'prestataire@example.test',
            'Jean Dupont',
            42,
            'Maintenance',
            'SITE-1',
            'Site 1',
        );

        $this->assertNotNull($sent);
        $this->assertStringNotContainsString('/planning/42', (string) $sent->getTextBody());
        $this->assertStringNotContainsString('href=', (string) $sent->getHtmlBody());
    }

    private function mailer(string $frontendUrl): PlanningMailer
    {
        return new PlanningMailer(
            $this->mailer,
            new NullLogger(),
            'noreply@toa.app',
            $frontendUrl,
        );
    }
}
