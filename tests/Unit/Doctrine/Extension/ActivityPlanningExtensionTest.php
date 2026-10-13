<?php

declare(strict_types=1);

namespace App\Tests\Unit\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use App\Doctrine\Extension\ActivityPlanningExtension;
use App\Domain\ActivityPlanning\Entity\ActivityPlanning;
use App\Domain\Entreprise\Entity\Entreprise;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Uuid;

/**
 * Couvre R-04 : un planning est créé par le chef de projet TOA, jamais par le
 * prestataire, donc `createdBy` ne vaut jamais l'utilisateur côté prestataire
 * et la collection GET /api/activity_plannings restait vide pour lui. Le
 * scope doit se faire sur `entreprise` (prestataire assigné au planning), pas
 * sur `createdBy`, pour tout utilisateur hors BYPASS_ROLES ayant une
 * entreprise renseignée.
 */
final class ActivityPlanningExtensionTest extends TestCase
{
    #[DataProvider('bypassRoleProvider')]
    public function testBypassRolesSeeEveryPlanningWithoutAnyFilter(string $role): void
    {
        $user = new User();
        $user->setEmail('bypass-' . $role . '@example.test');
        $user->setRoles([$role]);

        $security = $this->securityFor($user, [$role]);
        $queryBuilder = $this->buildQueryBuilder();

        (new ActivityPlanningExtension($security))->applyToCollection(
            $queryBuilder,
            new QueryNameGenerator(),
            ActivityPlanning::class,
        );

        $dql = $queryBuilder->getDQL();
        $this->assertStringNotContainsString('createdBy', $dql);
        $this->assertStringNotContainsString('entreprise', $dql);
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

    public function testPrestataireWithEntrepriseIsScopedOnEntrepriseNotCreatedBy(): void
    {
        $entreprise = $this->buildEntreprise();
        $user = new User();
        $user->setEmail('prestataire@example.test');
        $user->setRoles(['ROLE_PRESTATAIRE']);
        $user->setEntreprise($entreprise);

        $security = $this->securityFor($user, []);
        $queryBuilder = $this->buildQueryBuilder();

        (new ActivityPlanningExtension($security))->applyToCollection(
            $queryBuilder,
            new QueryNameGenerator(),
            ActivityPlanning::class,
        );

        $dql = $queryBuilder->getDQL();
        $this->assertStringContainsString('o.entreprise = :ap_current_entreprise', $dql);
        $this->assertStringNotContainsString('createdBy', $dql);
        $this->assertSame($entreprise, $queryBuilder->getParameter('ap_current_entreprise')?->getValue());
    }

    public function testUserWithoutEntrepriseFallsBackToStrictCreatedByScope(): void
    {
        $user = new User();
        $user->setEmail('solo@example.test');
        $user->setRoles(['ROLE_PRESTATAIRE']);
        // Deliberately no entreprise set (cas anormal).

        $security = $this->securityFor($user, []);
        $queryBuilder = $this->buildQueryBuilder();

        (new ActivityPlanningExtension($security))->applyToCollection(
            $queryBuilder,
            new QueryNameGenerator(),
            ActivityPlanning::class,
        );

        $dql = $queryBuilder->getDQL();
        $this->assertStringContainsString('o.createdBy = :ap_current_user', $dql);
        $this->assertStringNotContainsString('entreprise', $dql);
        $this->assertSame($user, $queryBuilder->getParameter('ap_current_user')?->getValue());
    }

    public function testIgnoresResourceClassesOtherThanActivityPlanning(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_PRESTATAIRE']);

        $security = $this->securityFor($user, []);
        $queryBuilder = $this->buildQueryBuilder();
        $dqlBefore = $queryBuilder->getDQL();

        (new ActivityPlanningExtension($security))->applyToCollection(
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
        $queryBuilder->select('o')->from(ActivityPlanning::class, 'o');

        return $queryBuilder;
    }
}
