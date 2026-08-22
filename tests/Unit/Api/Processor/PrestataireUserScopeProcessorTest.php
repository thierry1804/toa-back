<?php

declare(strict_types=1);

namespace App\Tests\Unit\Api\Processor;

use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Processor\PrestataireUserScopeProcessor;
use App\Domain\Entreprise\Entity\Entreprise;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class PrestataireUserScopeProcessorTest extends TestCase
{
    private ProcessorInterface $inner;
    private TokenStorageInterface $tokenStorage;
    private UserRepository $userRepository;
    private PrestataireUserScopeProcessor $processor;

    protected function setUp(): void
    {
        $this->inner = $this->createMock(ProcessorInterface::class);
        $this->inner->method('process')->willReturnArgument(0);

        $this->tokenStorage  = $this->createMock(TokenStorageInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);

        $this->processor = new PrestataireUserScopeProcessor(
            $this->inner,
            $this->tokenStorage,
            $this->userRepository,
        );
    }

    public function testCreateIgnoresSubmittedRoleAndEntreprise(): void
    {
        $ownEntreprise = $this->buildEntreprise();
        $actor = $this->buildPrestataire($ownEntreprise, id: 1);
        $actor->setEntrepriseName('Ma Boite');
        $actor->setQualiteRepresentant('Gérant');
        $this->setActor($actor);

        $otherEntreprise = $this->buildEntreprise();
        $newUser = new User();
        $newUser->setRoles(['ROLE_SUPER_ADMIN']);
        $newUser->setEntreprise($otherEntreprise);
        $newUser->setEntrepriseName('Boite Rivale');
        $newUser->setQualiteRepresentant('PDG');

        $result = $this->processor->process($newUser, new Post());

        $this->assertEqualsCanonicalizing(['ROLE_PRESTATAIRE', 'ROLE_USER'], $result->getRoles());
        $this->assertSame($ownEntreprise, $result->getEntreprise());
        $this->assertSame('Ma Boite', $result->getEntrepriseName());
        $this->assertSame('Gérant', $result->getQualiteRepresentant());
    }

    public function testEditingTeammateIgnoresSubmittedRoleAndEntreprise(): void
    {
        $ownEntreprise = $this->buildEntreprise();
        $actor = $this->buildPrestataire($ownEntreprise, id: 1);
        $actor->setEntrepriseName('Ma Boite');
        $this->setActor($actor);

        $otherEntreprise = $this->buildEntreprise();
        $teammate = $this->buildPrestataire($ownEntreprise, id: 2);
        $teammate->setRoles(['ROLE_SUPER_ADMIN']);
        $teammate->setEntreprise($otherEntreprise);
        $teammate->setEntrepriseName('Boite Rivale');

        $result = $this->processor->process($teammate, new Patch(), ['id' => 2]);

        $this->assertEqualsCanonicalizing(['ROLE_PRESTATAIRE', 'ROLE_USER'], $result->getRoles());
        $this->assertSame($ownEntreprise, $result->getEntreprise());
        $this->assertSame('Ma Boite', $result->getEntrepriseName());
    }

    public function testSelfEditCanUpdateOwnDisplayFieldsWhenLinkingEntreprise(): void
    {
        $actor = $this->buildPrestataire(null, id: 1);
        $this->setActor($actor);
        $this->userRepository->method('findBy')->willReturn([]);

        $ownEntreprise = $this->buildEntreprise();
        $self = $this->buildPrestataire(null, id: 1);
        $self->setEntreprise($ownEntreprise);
        $self->setEntrepriseName('Ma Nouvelle Boite');

        $result = $this->processor->process($self, new Patch(), ['id' => 1]);

        $this->assertSame('Ma Nouvelle Boite', $result->getEntrepriseName());
    }

    public function testSelfEditCannotEscalateRole(): void
    {
        $ownEntreprise = $this->buildEntreprise();
        $actor = $this->buildPrestataire($ownEntreprise, id: 1);
        $this->setActor($actor);

        $self = $this->buildPrestataire($ownEntreprise, id: 1);
        $self->setRoles(['ROLE_SUPER_ADMIN']);

        $result = $this->processor->process($self, new Patch(), ['id' => 1]);

        $this->assertEqualsCanonicalizing(['ROLE_PRESTATAIRE', 'ROLE_USER'], $result->getRoles());
    }

    public function testSelfEditCannotHijackAlreadyClaimedEntreprise(): void
    {
        $actor = $this->buildPrestataire(null, id: 1);
        $this->setActor($actor);

        $rivalEntreprise = $this->buildEntreprise();
        $otherOwner = $this->buildPrestataire($rivalEntreprise, id: 2);

        $this->userRepository
            ->method('findBy')
            ->with(['entreprise' => $rivalEntreprise->getId()])
            ->willReturn([$otherOwner]);

        $self = $this->buildPrestataire(null, id: 1);
        $self->setEntreprise($rivalEntreprise);

        $result = $this->processor->process($self, new Patch(), ['id' => 1]);

        $this->assertNull($result->getEntreprise());
    }

    public function testAdminIsNeverScoped(): void
    {
        $admin = $this->buildPrestataire(null, id: 1);
        $admin->setRoles(['ROLE_PRESTATAIRE', 'ROLE_ADMIN']);
        $this->setActor($admin);

        $entreprise = $this->buildEntreprise();
        $newUser = new User();
        $newUser->setRoles(['ROLE_SUPER_ADMIN']);
        $newUser->setEntreprise($entreprise);

        $result = $this->processor->process($newUser, new Post());

        $this->assertEqualsCanonicalizing(['ROLE_SUPER_ADMIN', 'ROLE_USER'], $result->getRoles());
        $this->assertSame($entreprise, $result->getEntreprise());
    }

    private function buildEntreprise(): Entreprise
    {
        $entreprise = new Entreprise();
        $entreprise->setNom('Entreprise ' . uniqid());

        $reflection = new \ReflectionProperty(Entreprise::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($entreprise, \Symfony\Component\Uid\Uuid::v7());

        return $entreprise;
    }

    private function buildPrestataire(?Entreprise $entreprise, int $id): User
    {
        $user = new User();
        $user->setEmail('prestataire-' . $id . '@toa.local');
        $user->setName('Prestataire');
        $user->setFirstname('Test');
        $user->setRoles(['ROLE_PRESTATAIRE']);
        $user->setEntreprise($entreprise);

        $reflection = new \ReflectionProperty(User::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($user, $id);

        return $user;
    }

    private function setActor(User $actor): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($actor);
        $this->tokenStorage->method('getToken')->willReturn($token);
    }
}
