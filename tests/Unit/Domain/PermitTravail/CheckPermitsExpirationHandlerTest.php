<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\PermitTravail;

use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Enum\StatutPermitTravail;
use App\Domain\PermitTravail\Enum\TypePermitTravail;
use App\Domain\PermitTravail\Message\CheckPermitsExpirationMessage;
use App\Domain\PermitTravail\MessageHandler\CheckPermitsExpirationHandler;
use App\Domain\PermitTravail\Repository\PermitTravailRepository;
use App\Domain\PermitTravail\Service\PermitTravailNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CheckPermitsExpirationHandlerTest extends TestCase
{
    private PermitTravailRepository $repository;
    private PermitTravailNotificationService $notificationService;
    private EntityManagerInterface $entityManager;
    private CheckPermitsExpirationHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PermitTravailRepository::class);
        $this->notificationService = $this->createMock(PermitTravailNotificationService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->handler = new CheckPermitsExpirationHandler(
            $this->repository,
            $this->notificationService,
            $this->entityManager,
            $this->createMock(LoggerInterface::class),
        );
    }

    public function testNotifiesAndMarksExpiringPermits(): void
    {
        $permit = $this->buildPermit();

        $this->repository
            ->expects($this->once())
            ->method('findExpirantSansNotification')
            ->willReturn([$permit]);

        $this->notificationService
            ->expects($this->once())
            ->method('notifierExpirationProchaine')
            ->with($permit);

        $this->entityManager->expects($this->once())->method('flush');

        ($this->handler)(new CheckPermitsExpirationMessage());

        $this->assertNotNull($permit->getNotificationExpirationEnvoyeeAt());
    }

    public function testDoesNothingWhenNoPermitIsExpiring(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findExpirantSansNotification')
            ->willReturn([]);

        $this->notificationService->expects($this->never())->method('notifierExpirationProchaine');
        $this->entityManager->expects($this->never())->method('flush');

        ($this->handler)(new CheckPermitsExpirationMessage());
    }

    private function buildPermit(): PermitTravail
    {
        $permit = new PermitTravail();
        $permit->setReference('PT-2026-001');
        $permit->setCodeSite('SITE01');
        $permit->setType(TypePermitTravail::HAUTEUR);
        $permit->setStatut(StatutPermitTravail::EN_COURS);
        $permit->setDateFinPrevue(new \DateTimeImmutable('+2 days'));

        return $permit;
    }
}
