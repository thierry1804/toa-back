<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\PlanPrevention\Service;

use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Service\PlanPreventionNotificationService;
use App\Domain\Entreprise\Entity\Entreprise;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class PlanPreventionNotificationServiceTest extends TestCase
{
    private UserRepository&MockObject $userRepository;
    private MailerInterface&MockObject $mailer;
    private PlanPreventionNotificationService $service;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->service = new PlanPreventionNotificationService(
            $this->mailer,
            $this->userRepository,
            new NullLogger(),
        );
    }

    private function hseUser(string $email, ?string $entrepriseNom, bool $interne = false): User
    {
        $user = (new User())->setEmail($email)->setRoles(['ROLE_HSE']);

        if ($entrepriseNom !== null) {
            $user->setEntreprise((new Entreprise())->setNom($entrepriseNom)->setInterne($interne));
        }

        return $user;
    }

    /** @return \ArrayObject<int, string> destinataires des emails envoyés */
    private function captureRecipients(): \ArrayObject
    {
        /** @var \ArrayObject<int, string> $recipients */
        $recipients = new \ArrayObject();
        $this->mailer->expects($this->atLeastOnce())->method('send')->willReturnCallback(
            static function (Email $message) use ($recipients): void {
                foreach ($message->getTo() as $address) {
                    $recipients[] = $address->getAddress();
                }
            },
        );

        return $recipients;
    }

    public function testNotifierHseNotifiesEveryHseUserWhateverTheEntreprise(): void
    {
        $this->userRepository->expects($this->once())
            ->method('findByRole')
            ->with('ROLE_HSE')
            ->willReturn([
                $this->hseUser('hse.interne@toa.mg', 'TOA', true),
                $this->hseUser('hse.presta@acme.mg', 'ACME'),
                $this->hseUser('hse.sans.entreprise@example.mg', null),
            ]);
        $this->userRepository->expects($this->never())->method('findByRoleInInternalEntreprises');
        $this->userRepository->expects($this->never())->method('findByRoleAndEntreprise');
        $recipients = $this->captureRecipients();

        $this->service->notifierHse(new PlanPrevention(), 2);

        $this->assertSame(
            ['hse.interne@toa.mg', 'hse.presta@acme.mg', 'hse.sans.entreprise@example.mg'],
            $recipients->getArrayCopy(),
        );
    }

    public function testNotifyHseUsersNotifiesEveryHseUserWhateverTheEntreprise(): void
    {
        $this->userRepository->expects($this->once())->method('findByRole')->with('ROLE_HSE')->willReturn([
            $this->hseUser('hse.a@one.mg', 'ONE'),
            $this->hseUser('hse.b@two.mg', 'TWO'),
        ]);
        $this->userRepository->expects($this->never())->method('findByRoleInInternalEntreprises');
        $this->userRepository->expects($this->never())->method('findByRoleAndEntreprise');
        $recipients = $this->captureRecipients();

        $chefProjet = (new User())->setEmail('chef@toa.mg');
        $this->service->notifyHseUsers(new PlanPrevention(), $chefProjet);

        $this->assertSame(['hse.a@one.mg', 'hse.b@two.mg'], $recipients->getArrayCopy());
    }

    public function testNoEmailIsSentWhenThereIsNoHseUser(): void
    {
        $this->userRepository->expects($this->once())->method('findByRole')->with('ROLE_HSE')->willReturn([]);
        $this->userRepository->expects($this->never())->method('findByRoleAndEntreprise');
        $this->mailer->expects($this->never())->method('send');

        $this->service->notifierHse(new PlanPrevention(), 1);
    }
}
