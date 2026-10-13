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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class PrestataireUserScopeProcessorTest extends TestCase
{
    private ProcessorInterface&MockObject $inner;
    private TokenStorageInterface&MockObject $tokenStorage;
    private UserRepository&MockObject $userRepository;
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
        $actor->setQualiteRepresentant('Gerant');
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
        $this->assertSame('Gerant', $result->getQualiteRepresentant());
    }

    public function testCreateAllowsChefProjetPrestataireAndAgentTerrainRoles(): void
    {
        $ownEntreprise = $this->buildEntreprise();
        $actor = $this->buildPrestataire($ownEntreprise, id: 1);
        $this->setActor($actor);

        foreach (['ROLE_CHEF_PROJET_PRESTATAIRE', 'ROLE_AGENT_TERRAIN'] as $role) {
            $newUser = new User();
            $newUser->setRoles([$role]);
            $newUser->setEntreprise($ownEntreprise);

            $result = $this->processor->process($newUser, new Post());

            $this->assertContains($role, $result->getRoles());
            $this->assertSame($ownEntreprise, $result->getEntreprise());
        }
    }

    /**
     * Arbitrage client formel (D1, retour client R-02) : un prestataire ne
     * peut plus attribuer ROLE_HSE a un subordonne (il le pouvait avant).
     * Toute tentative, a la creation, retombe sur ROLE_PRESTATAIRE.
     */
    public function testCreateRejectsHseAndFallsBackToPrestataire(): void
    {
        $ownEntreprise = $this->buildEntreprise();
        $actor = $this->buildPrestataire($ownEntreprise, id: 1);
        $this->setActor($actor);

        $newUser = new User();
        $newUser->setRoles(['ROLE_HSE']);
        $newUser->setEntreprise($ownEntreprise);

        $result = $this->processor->process($newUser, new Post());

        $this->assertEqualsCanonicalizing(['ROLE_PRESTATAIRE', 'ROLE_USER'], $result->getRoles());
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

    /**
     * Arbitrage D1 (R-02) appliquee a une tentative de nouvelle attribution
     * via PATCH : un teammate qui n'avait pas ROLE_HSE avant ne peut pas se
     * le voir attribuer, meme si le prestataire le soumet explicitement.
     */
    public function testEditingTeammateRejectsNewlyAssignedHseAndFallsBackToPrestataire(): void
    {
        $ownEntreprise = $this->buildEntreprise();
        $actor = $this->buildPrestataire($ownEntreprise, id: 1);
        $this->setActor($actor);

        $previous = $this->buildPrestataire($ownEntreprise, id: 2);
        $previous->setRoles(['ROLE_AGENT_TERRAIN']);

        $teammate = $this->buildPrestataire($ownEntreprise, id: 2);
        $teammate->setRoles(['ROLE_HSE']);

        $result = $this->processor->process(
            $teammate,
            new Patch(),
            ['id' => 2],
            ['previous_data' => $previous],
        );

        $this->assertEqualsCanonicalizing(['ROLE_PRESTATAIRE', 'ROLE_USER'], $result->getRoles());
    }

    /**
     * Regression guard for a side effect of the ASSIGNABLE_ROLES change: a
     * teammate who already had ROLE_HSE before this feature existed (granted
     * back when a prestataire could still assign it) must not be silently
     * demoted to ROLE_PRESTATAIRE by an unrelated PATCH (e.g. toggling
     * isActive) that never touched the role field — only an actual attempt
     * to CHANGE the role to something new is rejected by the arbitrage.
     */
    public function testEditingTeammateWithoutChangingRoleDoesNotDemoteAnExistingHse(): void
    {
        $ownEntreprise = $this->buildEntreprise();
        $actor = $this->buildPrestataire($ownEntreprise, id: 1);
        $this->setActor($actor);

        $previous = $this->buildPrestataire($ownEntreprise, id: 2);
        $previous->setRoles(['ROLE_HSE']);

        // Same role as before the PATCH: the client did not submit a role
        // change, API Platform's PATCH merge kept the already-persisted role.
        // isActive stays true (untouched) so the last-active-HSE guard -- a
        // separate concern, covered by its own tests below -- never engages
        // here; this test is purely about role preservation.
        $teammate = $this->buildPrestataire($ownEntreprise, id: 2);
        $teammate->setRoles(['ROLE_HSE']);
        $teammate->setName('Nouveau Nom');

        $this->userRepository->expects($this->never())->method('findByRoleAndEntreprise');

        $result = $this->processor->process(
            $teammate,
            new Patch(),
            ['id' => 2],
            ['previous_data' => $previous],
        );

        $this->assertContains('ROLE_HSE', $result->getRoles());
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

    public function testDeactivatingLastActiveHseThrows(): void
    {
        $ownEntreprise = $this->buildEntreprise();
        $actor = $this->buildPrestataire($ownEntreprise, id: 1);
        $this->setActor($actor);

        $previous = $this->buildPrestataire($ownEntreprise, id: 2);
        $previous->setRoles(['ROLE_HSE']);

        $lastHse = $this->buildPrestataire($ownEntreprise, id: 2);
        $lastHse->setRoles(['ROLE_HSE']);
        $lastHse->setIsActive(false);

        $this->userRepository
            ->method('findByRoleAndEntreprise')
            ->with('ROLE_HSE', $ownEntreprise->getId())
            ->willReturn([$lastHse]);

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('user.last_hse_cannot_deactivate');

        $this->processor->process($lastHse, new Patch(), ['id' => 2], ['previous_data' => $previous]);
    }

    public function testDeactivatingHseWithAnotherActiveHseDoesNotThrow(): void
    {
        $ownEntreprise = $this->buildEntreprise();
        $actor = $this->buildPrestataire($ownEntreprise, id: 1);
        $this->setActor($actor);

        $previous = $this->buildPrestataire($ownEntreprise, id: 2);
        $previous->setRoles(['ROLE_HSE']);

        $target = $this->buildPrestataire($ownEntreprise, id: 2);
        $target->setRoles(['ROLE_HSE']);
        $target->setIsActive(false);

        $colleague = $this->buildPrestataire($ownEntreprise, id: 3);
        $colleague->setRoles(['ROLE_HSE']);
        $colleague->setIsActive(true);

        $this->userRepository
            ->method('findByRoleAndEntreprise')
            ->with('ROLE_HSE', $ownEntreprise->getId())
            ->willReturn([$target, $colleague]);

        $result = $this->processor->process($target, new Patch(), ['id' => 2], ['previous_data' => $previous]);

        $this->assertFalse($result->getIsActive());
    }

    public function testDeactivatingHseWithOnlyInactiveColleagueThrows(): void
    {
        $ownEntreprise = $this->buildEntreprise();
        $actor = $this->buildPrestataire($ownEntreprise, id: 1);
        $this->setActor($actor);

        $previous = $this->buildPrestataire($ownEntreprise, id: 2);
        $previous->setRoles(['ROLE_HSE']);

        $target = $this->buildPrestataire($ownEntreprise, id: 2);
        $target->setRoles(['ROLE_HSE']);
        $target->setIsActive(false);

        $inactiveColleague = $this->buildPrestataire($ownEntreprise, id: 3);
        $inactiveColleague->setRoles(['ROLE_HSE']);
        $inactiveColleague->setIsActive(false);

        $this->userRepository
            ->method('findByRoleAndEntreprise')
            ->with('ROLE_HSE', $ownEntreprise->getId())
            ->willReturn([$target, $inactiveColleague]);

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('user.last_hse_cannot_deactivate');

        $this->processor->process($target, new Patch(), ['id' => 2], ['previous_data' => $previous]);
    }

    public function testReactivatingHseIsNeverBlocked(): void
    {
        $ownEntreprise = $this->buildEntreprise();
        $actor = $this->buildPrestataire($ownEntreprise, id: 1);
        $this->setActor($actor);

        $previous = $this->buildPrestataire($ownEntreprise, id: 2);
        $previous->setRoles(['ROLE_HSE']);

        $target = $this->buildPrestataire($ownEntreprise, id: 2);
        $target->setRoles(['ROLE_HSE']);
        $target->setIsActive(true);

        $this->userRepository->expects($this->never())->method('findByRoleAndEntreprise');

        $result = $this->processor->process($target, new Patch(), ['id' => 2], ['previous_data' => $previous]);

        $this->assertTrue($result->getIsActive());
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
