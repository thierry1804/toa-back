<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Domain\ActivityPlanning\Entity\ActivityPlanning;
use App\Domain\Entreprise\Entity\Entreprise;
use App\Domain\Menu\Service\PermissionChecker;
use App\Domain\User\Entity\User;
use App\Security\Voter\ActivityPlanningVoter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;

/**
 * Couvre R-04 : un planning est créé par le chef de projet TOA, jamais par le
 * prestataire. ActivityPlanningVoter doit donc accorder VIEW à un prestataire
 * qui n'est pas le créateur, du moment que le planning est assigné à son
 * entreprise (champ ActivityPlanning::$entreprise), et continuer à refuser
 * l'accès à un planning d'une autre entreprise.
 */
final class ActivityPlanningVoterTest extends TestCase
{
    private PermissionChecker&MockObject $permissionChecker;
    private ActivityPlanningVoter $voter;

    protected function setUp(): void
    {
        $this->permissionChecker = $this->createMock(PermissionChecker::class);
        $this->voter = new ActivityPlanningVoter($this->permissionChecker);
    }

    public function testCreatorCanViewOwnPlanning(): void
    {
        $creator = $this->buildUser('chef-projet@toa.local', 1);
        $planning = $this->buildPlanning($creator, null);
        $this->permissionChecker->method('isGranted')->willReturn(true);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->mockToken($creator), $planning, [ActivityPlanningVoter::VIEW]),
        );
    }

    public function testPrestataireOfSameEntrepriseCanViewPlanningCreatedByAnotherChefProjet(): void
    {
        $entreprise = $this->buildEntreprise();
        $chefProjet = $this->buildUser('chef-projet@toa.local', 1);
        $prestataire = $this->buildUser('prestataire@example.test', 2, $entreprise);
        $planning = $this->buildPlanning($chefProjet, $entreprise);
        $this->permissionChecker->method('isGranted')->willReturn(true);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->mockToken($prestataire), $planning, [ActivityPlanningVoter::VIEW]),
        );
    }

    public function testPrestataireOfDifferentEntrepriseIsDenied(): void
    {
        $entreprise = $this->buildEntreprise();
        $otherEntreprise = $this->buildEntreprise();
        $chefProjet = $this->buildUser('chef-projet@toa.local', 1);
        $prestataire = $this->buildUser('prestataire@example.test', 2, $otherEntreprise);
        $planning = $this->buildPlanning($chefProjet, $entreprise);
        $this->permissionChecker->method('isGranted')->willReturn(true);

        $this->expectException(AccessDeniedException::class);
        $this->voter->vote($this->mockToken($prestataire), $planning, [ActivityPlanningVoter::VIEW]);
    }

    public function testPrestataireWithoutEntrepriseIsDeniedWhenNotCreator(): void
    {
        $chefProjet = $this->buildUser('chef-projet@toa.local', 1);
        $prestataireSansEntreprise = $this->buildUser('solo@example.test', 2, null);
        $planning = $this->buildPlanning($chefProjet, null);
        $this->permissionChecker->method('isGranted')->willReturn(true);

        $this->expectException(AccessDeniedException::class);
        $this->voter->vote($this->mockToken($prestataireSansEntreprise), $planning, [ActivityPlanningVoter::VIEW]);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('bypassRoleProvider')]
    public function testBypassRolesAlwaysSeeAnyPlanningRegardlessOfEntreprise(string $role): void
    {
        $entreprise = $this->buildEntreprise();
        $otherEntreprise = $this->buildEntreprise();
        $chefProjet = $this->buildUser('chef-projet@toa.local', 1);
        $bypassUser = $this->buildUser('bypass@toa.local', 2, $otherEntreprise);
        $bypassUser->setRoles([$role]);
        $planning = $this->buildPlanning($chefProjet, $entreprise);
        $this->permissionChecker->method('isGranted')->willReturn(true);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->mockToken($bypassUser), $planning, [ActivityPlanningVoter::VIEW]),
        );
    }

    public static function bypassRoleProvider(): array
    {
        return [
            ['ROLE_SUPER_ADMIN'],
            ['ROLE_ADMIN'],
            ['ROLE_HSE'],
            ['ROLE_DG'],
        ];
    }

    public function testMenuPermissionDeniedThrowsRegardlessOfOwnership(): void
    {
        $creator = $this->buildUser('chef-projet@toa.local', 1);
        $planning = $this->buildPlanning($creator, null);
        $this->permissionChecker->method('isGranted')->willReturn(false);

        $this->expectException(AccessDeniedException::class);
        $this->voter->vote($this->mockToken($creator), $planning, [ActivityPlanningVoter::VIEW]);
    }

    private function buildEntreprise(): Entreprise
    {
        $entreprise = new Entreprise();
        $entreprise->setNom('Entreprise ' . uniqid());

        $reflection = new \ReflectionProperty(Entreprise::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($entreprise, Uuid::v7());

        return $entreprise;
    }

    private function buildPlanning(User $createdBy, ?Entreprise $entreprise): ActivityPlanning
    {
        $planning = new ActivityPlanning();
        $planning->setProcess('Process X');
        $planning->setProvider('Provider X');
        $planning->setSiteCode('SITE-1');
        $planning->setSiteName('Site 1');
        $planning->setExpectedStartDate(new \DateTimeImmutable('+1 day'));
        $planning->setExpectedEndDate(new \DateTimeImmutable('+2 days'));
        $planning->setCreatedBy($createdBy);
        $planning->setEntreprise($entreprise);

        $reflection = new \ReflectionProperty(ActivityPlanning::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($planning, 1);

        return $planning;
    }

    private function buildUser(string $email, int $id, ?Entreprise $entreprise = null): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setName('Test');
        $user->setFirstname('User');
        $user->setRoles(['ROLE_PRESTATAIRE']);
        $user->setEntreprise($entreprise);

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
