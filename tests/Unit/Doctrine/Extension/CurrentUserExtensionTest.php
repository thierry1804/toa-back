<?php

declare(strict_types=1);

namespace App\Tests\Unit\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use App\Doctrine\Extension\CurrentUserExtension;
use App\Domain\Entreprise\Entity\Entreprise;
use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Uuid;

/**
 * Covers R-01/R-02 follow-up: GET /api/permits_travails must expose every
 * permit created by a prestataire-side user's own entreprise (whole team),
 * not just the ones they personally created — needed for the new
 * ROLE_CHEF_PROJET_PRESTATAIRE (read-only, never the creator of any permit)
 * to see anything at all. Aligned with
 * PermitTravailVoter::hasOwnership() (creator OR same entreprise).
 */
final class CurrentUserExtensionTest extends TestCase
{
    #[DataProvider('bypassRoleProvider')]
    public function testBypassRolesSeeEveryPermitWithoutAnyFilter(string $role): void
    {
        $user = new User();
        $user->setRoles([$role]);

        $security = $this->securityFor($user, [$role]);
        $queryBuilder = $this->buildQueryBuilder();

        (new CurrentUserExtension($security))->applyToCollection(
            $queryBuilder,
            new QueryNameGenerator(),
            PermitTravail::class,
        );

        $this->assertStringNotContainsString('createdBy', $queryBuilder->getDQL());
    }

    public static function bypassRoleProvider(): array
    {
        return [
            ['ROLE_SUPER_ADMIN'],
            ['ROLE_ADMIN'],
            ['ROLE_HSE'],
            ['ROLE_CHEF_PROJET'],
        ];
    }

    #[DataProvider('prestataireSideRoleProvider')]
    public function testPrestataireSideRolesWithEntrepriseSeeTheWholeTeamNotJustOwnPermits(string $role): void
    {
        $entreprise = $this->buildEntreprise();
        $user = new User();
        $user->setEmail('user-' . $role . '@example.test');
        $user->setRoles([$role]);
        $user->setEntreprise($entreprise);

        $security = $this->securityFor($user, []);
        $queryBuilder = $this->buildQueryBuilder();

        (new CurrentUserExtension($security))->applyToCollection(
            $queryBuilder,
            new QueryNameGenerator(),
            PermitTravail::class,
        );

        $dql = $queryBuilder->getDQL();
        $this->assertStringContainsString('o.createdBy', $dql);
        $this->assertStringContainsString('cue_creator.entreprise = :current_entreprise', $dql);
        $this->assertStringNotContainsString('o.createdBy = :current_user', $dql);
        $this->assertSame($entreprise, $queryBuilder->getParameter('current_entreprise')?->getValue());
    }

    public static function prestataireSideRoleProvider(): array
    {
        return [
            ['ROLE_PRESTATAIRE'],
            ['ROLE_CHEF_PROJET_PRESTATAIRE'],
            ['ROLE_AGENT_TERRAIN'],
        ];
    }

    public function testUserWithoutEntrepriseFallsBackToStrictPerUserScope(): void
    {
        $user = new User();
        $user->setEmail('solo@example.test');
        $user->setRoles(['ROLE_PRESTATAIRE']);
        // Deliberately no entreprise set.

        $security = $this->securityFor($user, []);
        $queryBuilder = $this->buildQueryBuilder();

        (new CurrentUserExtension($security))->applyToCollection(
            $queryBuilder,
            new QueryNameGenerator(),
            PermitTravail::class,
        );

        $dql = $queryBuilder->getDQL();
        $this->assertStringContainsString('o.createdBy = :current_user', $dql);
        $this->assertStringNotContainsString('entreprise', $dql);
        $this->assertSame($user, $queryBuilder->getParameter('current_user')?->getValue());
    }

    public function testIgnoresResourceClassesOtherThanPermitTravail(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_PRESTATAIRE']);

        $security = $this->securityFor($user, []);
        $queryBuilder = $this->buildQueryBuilder();
        $dqlBefore = $queryBuilder->getDQL();

        (new CurrentUserExtension($security))->applyToCollection(
            $queryBuilder,
            new QueryNameGenerator(),
            \App\Domain\PlanPrevention\Entity\PlanPrevention::class,
        );

        $this->assertSame($dqlBefore, $queryBuilder->getDQL());
    }

    private function securityFor(User $user, array $grantedRoles): Security
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);
        $security->method('isGranted')->willReturnCallback(
            static fn (string $role): bool => in_array($role, $grantedRoles, true),
        );

        return $security;
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

    private function buildQueryBuilder(): QueryBuilder
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getExpressionBuilder')->willReturn(new Expr());
        $queryBuilder = new QueryBuilder($entityManager);
        $queryBuilder->select('o')->from(PermitTravail::class, 'o');

        return $queryBuilder;
    }
}
