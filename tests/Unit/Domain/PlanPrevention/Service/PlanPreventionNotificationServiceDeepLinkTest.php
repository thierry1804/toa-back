<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\PlanPrevention\Service;

use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Service\PlanPreventionNotificationService;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Uid\Uuid;

class PlanPreventionNotificationServiceDeepLinkTest extends TestCase
{
    private MailerInterface&MockObject $mailer;
    private UserRepository&MockObject $userRepository;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
    }

    public function testValidationContextIncludesPlanUrlWithoutLocalhost(): void
    {
        $service = $this->service('https://toa-front.dev.arkeup.com');
        $prestataire = (new User())->setEmail('p@acme.mg');
        $plan = $this->planWithId()->setCreatedBy($prestataire)->setReference('PP-1');

        $context = null;
        $this->mailer->expects($this->once())->method('send')->willReturnCallback(
            static function (TemplatedEmail $message) use (&$context): void {
                $context = $message->getContext();
            },
        );

        $service->notifierValidation($plan);

        $expected = 'https://toa-front.dev.arkeup.com/prevention/' . $plan->getId()->toRfc4122();
        $this->assertIsArray($context);
        $this->assertSame($expected, $context['plan_url'] ?? null);
        $this->assertStringNotContainsString('localhost', (string) $context['plan_url']);
    }

    public function testAValiderContextIncludesPlanUrl(): void
    {
        $service = $this->service('https://toa-front.dev.arkeup.com');
        $plan = $this->planWithId()->setReference('PP-2');
        $chef = (new User())->setEmail('chef@toa.mg');
        $hse = (new User())->setEmail('hse@toa.mg')->setRoles(['ROLE_HSE']);
        $this->userRepository->method('findByRole')->with('ROLE_HSE')->willReturn([$hse]);

        $context = null;
        $this->mailer->expects($this->once())->method('send')->willReturnCallback(
            static function (TemplatedEmail $message) use (&$context): void {
                $context = $message->getContext();
            },
        );

        $service->notifyHseUsers($plan, $chef);

        $expected = 'https://toa-front.dev.arkeup.com/prevention/' . $plan->getId()->toRfc4122();
        $this->assertSame($expected, $context['plan_url'] ?? null);
    }

    public function testEmptyFrontendUrlYieldsNullPlanUrl(): void
    {
        $service = $this->service('');
        $prestataire = (new User())->setEmail('p@acme.mg');
        $plan = $this->planWithId()->setCreatedBy($prestataire);

        $context = null;
        $this->mailer->expects($this->once())->method('send')->willReturnCallback(
            static function (TemplatedEmail $message) use (&$context): void {
                $context = $message->getContext();
            },
        );

        $service->notifierValidation($plan);

        $this->assertIsArray($context);
        $this->assertTrue(
            !array_key_exists('plan_url', $context) || $context['plan_url'] === null,
            'plan_url doit être absent ou null quand FRONTEND_URL est vide',
        );
    }

    private function service(string $frontendUrl): PlanPreventionNotificationService
    {
        return new PlanPreventionNotificationService(
            $this->mailer,
            $this->userRepository,
            new NullLogger(),
            'noreply@toa.app',
            $frontendUrl,
        );
    }

    private function planWithId(): PlanPrevention
    {
        $plan = new PlanPrevention();
        $idProp = new \ReflectionProperty(PlanPrevention::class, 'id');
        $idProp->setAccessible(true);
        $idProp->setValue($plan, Uuid::v7());

        return $plan;
    }
}
