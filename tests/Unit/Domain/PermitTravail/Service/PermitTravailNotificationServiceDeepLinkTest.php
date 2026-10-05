<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\PermitTravail\Service;

use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Service\PermitTravailNotificationService;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Uid\Uuid;

class PermitTravailNotificationServiceDeepLinkTest extends TestCase
{
    private MailerInterface&MockObject $mailer;
    private UserRepository&MockObject $userRepository;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
    }

    public function testValidationHtmlContainsFrontendDeepLink(): void
    {
        $service = $this->service('https://toa-front.dev.arkeup.com');
        $permit = $this->permitWithId();
        $prestataire = (new User())->setEmail('p@acme.mg')->setFirstname('Jean')->setName('Rakoto');
        $permit->setCreatedBy($prestataire);

        $html = null;
        $this->mailer->expects($this->once())->method('send')->willReturnCallback(
            static function (Email $message) use (&$html): void {
                $html = $message->getHtmlBody();
            },
        );

        $service->notifierValidation($permit);

        $expected = 'https://toa-front.dev.arkeup.com/permits-travail/' . $permit->getId()->toRfc4122();
        $this->assertIsString($html);
        $this->assertStringContainsString($expected, (string) $html);
        $this->assertStringContainsString('Ouvrir le permis validé', (string) $html);
        $this->assertStringNotContainsString('localhost', (string) $html);
    }

    public function testSoumissionHtmlContainsAValiderDeepLink(): void
    {
        $service = $this->service('https://toa-front.dev.arkeup.com');
        $permit = $this->permitWithId();
        $hse = (new User())->setEmail('hse@toa.mg')->setFirstname('Hse')->setName('Toa')->setRoles(['ROLE_HSE']);
        $this->userRepository->method('findByRole')->with('ROLE_HSE')->willReturn([$hse]);

        $html = null;
        $this->mailer->expects($this->once())->method('send')->willReturnCallback(
            static function (Email $message) use (&$html): void {
                $html = $message->getHtmlBody();
            },
        );

        $service->notifierSoumission($permit);

        $expected = 'https://toa-front.dev.arkeup.com/permits-travail/' . $permit->getId()->toRfc4122();
        $this->assertIsString($html);
        $this->assertStringContainsString($expected, (string) $html);
        $this->assertStringContainsString('Ouvrir le permis à valider', (string) $html);
    }

    public function testEmptyFrontendUrlOmitsDeepLink(): void
    {
        $service = $this->service('');
        $permit = $this->permitWithId();
        $prestataire = (new User())->setEmail('p@acme.mg')->setFirstname('Jean')->setName('Rakoto');
        $permit->setCreatedBy($prestataire);

        $html = null;
        $this->mailer->expects($this->once())->method('send')->willReturnCallback(
            static function (Email $message) use (&$html): void {
                $html = $message->getHtmlBody();
            },
        );

        $service->notifierValidation($permit);

        $this->assertIsString($html);
        $this->assertStringNotContainsString('permits-travail/', (string) $html);
        $this->assertStringNotContainsString('localhost', (string) $html);
    }

    private function service(string $frontendUrl): PermitTravailNotificationService
    {
        return new PermitTravailNotificationService(
            $this->mailer,
            $this->userRepository,
            new NullLogger(),
            'noreply@toa.app',
            $frontendUrl,
        );
    }

    private function permitWithId(): PermitTravail
    {
        $permit = (new PermitTravail())->setReference('PT-001')->setCodeSite('SITE-1');
        $idProp = new \ReflectionProperty(PermitTravail::class, 'id');
        $idProp->setAccessible(true);
        $idProp->setValue($permit, Uuid::v7());

        return $permit;
    }
}
