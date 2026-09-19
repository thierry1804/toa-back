<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Domain\Menu\Service\PermissionChecker;
use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Enum\ProcessusPermitTravail;
use App\Domain\PermitTravail\Enum\StatutPermitTravail;
use App\Domain\PermitTravail\Enum\TypePermitTravail;
use App\Domain\PermitTravail\Repository\PvReceptionPdfRepository;
use App\Domain\Role\Entity\RoleAction;
use App\Domain\User\Entity\User;
use App\Security\Voter\PermitTravailVoter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;

class PermitTravailVoterTest extends TestCase
{
    private PermissionChecker&MockObject $permissionChecker;
    private PvReceptionPdfRepository&MockObject $pvRepository;
    private PermitTravailVoter $voter;

    protected function setUp(): void
    {
        $this->permissionChecker = $this->createMock(PermissionChecker::class);
        $this->pvRepository = $this->createMock(PvReceptionPdfRepository::class);
        $this->voter = new PermitTravailVoter($this->permissionChecker, $this->pvRepository);
    }

    public function testCreatorCanDeleteOwnBrouillon(): void
    {
        $owner = $this->buildUser('owner@toa.local', 1);
        $permit = $this->buildPermit($owner, StatutPermitTravail::BROUILLON);
        $this->mockRoleActions(bypass: false);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->mockToken($owner), $permit, [PermitTravailVoter::DELETE]),
        );
    }

    public function testOtherUserCannotDeleteSomeoneElsesBrouillon(): void
    {
        $owner = $this->buildUser('owner@toa.local', 1);
        $stranger = $this->buildUser('stranger@toa.local', 2);
        $permit = $this->buildPermit($owner, StatutPermitTravail::BROUILLON);
        $this->mockRoleActions(bypass: false);

        $this->expectException(AccessDeniedException::class);
        $this->voter->vote($this->mockToken($stranger), $permit, [PermitTravailVoter::DELETE]);
    }

    public function testSameEntrepriseTeammateCannotDelete(): void
    {
        // Contrairement à VIEW/EDIT (équipe autorisée en lecture), DELETE reste
        // strictement réservé au créateur — un collègue de la même entreprise
        // ne doit PAS pouvoir supprimer le permis d'un autre.
        $owner = $this->buildUser('owner@toa.local', 1);
        $teammate = $this->buildUser('teammate@toa.local', 2);
        $permit = $this->buildPermit($owner, StatutPermitTravail::BROUILLON);
        $this->mockRoleActions(bypass: false);

        $this->expectException(AccessDeniedException::class);
        $this->voter->vote($this->mockToken($teammate), $permit, [PermitTravailVoter::DELETE]);
    }

    public function testCannotDeleteSubmittedPermit(): void
    {
        $owner = $this->buildUser('owner@toa.local', 1);
        $permit = $this->buildPermit($owner, StatutPermitTravail::SOUMIS);
        $this->mockRoleActions(bypass: false);

        $this->expectException(ConflictHttpException::class);
        $this->voter->vote($this->mockToken($owner), $permit, [PermitTravailVoter::DELETE]);
    }

    public function testCannotDeleteValidatedPermit(): void
    {
        $owner = $this->buildUser('owner@toa.local', 1);
        $permit = $this->buildPermit($owner, StatutPermitTravail::VALIDE_HSE);
        $this->mockRoleActions(bypass: false);

        $this->expectException(ConflictHttpException::class);
        $this->voter->vote($this->mockToken($owner), $permit, [PermitTravailVoter::DELETE]);
    }

    public function testBypassRoleCanDeleteAnyonesBrouillon(): void
    {
        $owner = $this->buildUser('owner@toa.local', 1);
        $hse = $this->buildUser('hse@toa.local', 2);
        $permit = $this->buildPermit($owner, StatutPermitTravail::BROUILLON);
        $this->mockRoleActions(bypass: true);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->mockToken($hse), $permit, [PermitTravailVoter::DELETE]),
        );
    }

    public function testNoRoleActionAtAllIsDenied(): void
    {
        $owner = $this->buildUser('owner@toa.local', 1);
        $permit = $this->buildPermit($owner, StatutPermitTravail::BROUILLON);
        $this->permissionChecker->method('getRoleActions')->willReturn([]);

        $this->expectException(AccessDeniedException::class);
        $this->voter->vote($this->mockToken($owner), $permit, [PermitTravailVoter::DELETE]);
    }

    private function mockRoleActions(bool $bypass): void
    {
        $roleAction = $this->createMock(RoleAction::class);
        $roleAction->method('isBypassOwnership')->willReturn($bypass);
        $this->permissionChecker->method('getRoleActions')->willReturn([$roleAction]);
    }

    private function buildPermit(User $createdBy, StatutPermitTravail $statut): PermitTravail
    {
        $permit = new PermitTravail();
        $permit->setCodeSite('SITE-1');
        $permit->setType(TypePermitTravail::GENERAL);
        $permit->setProcessus(ProcessusPermitTravail::MAINTENANCE);
        $permit->setStatut($statut);
        $permit->setCreatedBy($createdBy);

        $reflection = new \ReflectionProperty(PermitTravail::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($permit, Uuid::v7());

        return $permit;
    }

    private function buildUser(string $email, int $id): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setName('Test');
        $user->setFirstname('User');
        $user->setRoles(['ROLE_PRESTATAIRE']);

        $reflection = new \ReflectionProperty(User::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($user, $id);

        return $user;
    }

    private function mockToken(User $user): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}
