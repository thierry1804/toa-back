<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Domain\Entreprise\Entity\Entreprise;
use App\Domain\Menu\Service\PermissionChecker;
use App\Domain\User\Entity\User;
use App\Security\Voter\EntrepriseVoter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;

class EntrepriseVoterTest extends TestCase
{
    private PermissionChecker&MockObject $permissionChecker;
    private EntrepriseVoter $voter;

    protected function setUp(): void
    {
        $this->permissionChecker = $this->createMock(PermissionChecker::class);
        $this->voter = new EntrepriseVoter($this->permissionChecker);
    }

    public function testPrestataireCanCreateWhenNoEntreprise(): void
    {
        $user = $this->buildPrestataire(null);
        $token = $this->mockToken($user);

        $this->permissionChecker
            ->method('isGranted')
            ->willReturn(false);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($token, null, [EntrepriseVoter::CREATE]),
        );
    }

    public function testPrestataireCannotCreateWhenEntrepriseAlreadyLinked(): void
    {
        $entreprise = $this->buildEntreprise();
        $user = $this->buildPrestataire($entreprise);
        $token = $this->mockToken($user);

        $this->permissionChecker
            ->method('isGranted')
            ->willReturn(false);

        $this->expectException(AccessDeniedException::class);
        $this->voter->vote($token, null, [EntrepriseVoter::CREATE]);
    }

    public function testPrestataireCanEditOwnEntreprise(): void
    {
        $entreprise = $this->buildEntreprise();
        $user = $this->buildPrestataire($entreprise);
        $token = $this->mockToken($user);

        $this->permissionChecker
            ->method('isGranted')
            ->willReturn(false);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($token, $entreprise, [EntrepriseVoter::EDIT]),
        );
    }

    public function testPrestataireCannotDelete(): void
    {
        $entreprise = $this->buildEntreprise();
        $user = $this->buildPrestataire($entreprise);
        $token = $this->mockToken($user);

        $this->permissionChecker
            ->method('isGranted')
            ->willReturn(false);

        $this->expectException(AccessDeniedException::class);
        $this->voter->vote($token, $entreprise, [EntrepriseVoter::DELETE]);
    }

    private function buildEntreprise(): Entreprise
    {
        $entreprise = new Entreprise();
        $entreprise->setNom('Acme');

        $reflection = new \ReflectionProperty(Entreprise::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($entreprise, Uuid::v7());

        return $entreprise;
    }

    private function buildPrestataire(?Entreprise $entreprise): User
    {
        $user = new User();
        $user->setEmail('prestataire@toa.local');
        $user->setName('Prestataire');
        $user->setFirstname('Test');
        $user->setRoles(['ROLE_PRESTATAIRE']);
        $user->setEntreprise($entreprise);

        $reflection = new \ReflectionProperty(User::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($user, 7);

        return $user;
    }

    private function mockToken(User $user): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}
