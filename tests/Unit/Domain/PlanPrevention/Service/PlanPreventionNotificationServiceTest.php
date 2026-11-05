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

    public function testNotifierValidationSendsConfirmationToPrestataire(): void
    {
        $prestataire = (new User())->setEmail('prestataire@acme.mg')->setFirstname('Jean')->setName('Rakoto');
        $plan = (new PlanPrevention())->setCreatedBy($prestataire);
        $recipients = $this->captureRecipients();

        $this->service->notifierValidation($plan);

        $this->assertSame(['prestataire@acme.mg'], $recipients->getArrayCopy());
    }

    public function testNotifierValidationDoesNothingWhenPlanHasNoCreator(): void
    {
        $this->mailer->expects($this->never())->method('send');

        $this->service->notifierValidation(new PlanPrevention());
    }

    public function testNotifierValidationNotifiesWholeEntrepriseTeamWhenEntrepriseIsKnown(): void
    {
        $entreprise = (new Entreprise())->setNom('ACME');
        $idProp = new \ReflectionProperty(Entreprise::class, 'id');
        $idProp->setAccessible(true);
        $idProp->setValue($entreprise, \Symfony\Component\Uid\Uuid::v7());

        $prestataire = $this->userWithId(1, (new User())->setEmail('presta@acme.mg')->setRoles(['ROLE_PRESTATAIRE'])->setEntreprise($entreprise));
        $chefPresta = $this->userWithId(2, (new User())->setEmail('chef.presta@acme.mg'));
        $agentTerrain = $this->userWithId(3, (new User())->setEmail('agent.terrain@acme.mg'));
        $plan = (new PlanPrevention())->setCreatedBy($prestataire);

        $this->userRepository->expects($this->exactly(3))
            ->method('findByRoleAndEntreprise')
            ->willReturnCallback(function (string $role, mixed $entrepriseId) use ($entreprise, $prestataire, $chefPresta, $agentTerrain) {
                $this->assertSame((string) $entreprise->getId(), (string) $entrepriseId);

                return match ($role) {
                    'ROLE_PRESTATAIRE' => [$prestataire],
                    'ROLE_CHEF_PROJET_PRESTATAIRE' => [$chefPresta],
                    'ROLE_AGENT_TERRAIN' => [$agentTerrain],
                    default => [],
                };
            });
        $recipients = $this->captureRecipients();

        $this->service->notifierValidation($plan);

        $this->assertSame(
            ['presta@acme.mg', 'chef.presta@acme.mg', 'agent.terrain@acme.mg'],
            $recipients->getArrayCopy(),
        );
    }

    public function testNotifierValidationDedupesAUserReturnedByMultipleRoleQueries(): void
    {
        $entreprise = (new Entreprise())->setNom('ACME');
        $idProp = new \ReflectionProperty(Entreprise::class, 'id');
        $idProp->setAccessible(true);
        $idProp->setValue($entreprise, \Symfony\Component\Uid\Uuid::v7());

        $prestataire = $this->userWithId(42, (new User())->setEmail('presta@acme.mg')->setEntreprise($entreprise));
        $plan = (new PlanPrevention())->setCreatedBy($prestataire);

        $this->userRepository->method('findByRoleAndEntreprise')->willReturn([$prestataire]);
        $recipients = $this->captureRecipients();

        $this->service->notifierValidation($plan);

        $this->assertSame(['presta@acme.mg'], $recipients->getArrayCopy());
    }

    private function userWithId(int $id, User $user): User
    {
        $idProp = new \ReflectionProperty(User::class, 'id');
        $idProp->setAccessible(true);
        $idProp->setValue($user, $id);

        return $user;
    }
}
