<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Intervention\Repository;

use App\Domain\Entreprise\Entity\Entreprise;
use App\Domain\Intervention\Entity\Intervention;
use App\Domain\Intervention\Repository\InterventionRepository;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Covers R-01: getDashboardData()/getActiveInterventionsBySite()/
 * countSuivisForDate() must scope non-HSE, prestataire-side users (incl. the
 * new read-only ROLE_CHEF_PROJET_PRESTATAIRE, which never creates a permit
 * itself) to every intervention whose permit was created by a member of
 * their own entreprise, not just permits where planPrevention.chefProjet
 * equals them (which never applies to a prestataire-side account), and
 * without regressing the existing pp.chefProjet scope for a TOA Chef de
 * Projet (no entreprise).
 *
 * These tests target the shared private applyOwnershipFilter() helper
 * directly via reflection and assert on the generated DQL/parameters, the
 * same technique already used for PlanPreventionExtensionTest — no real
 * database connection is available in this environment, so the full
 * getDashboardData()/getActiveInterventionsBySite() methods (which execute
 * the query) cannot be exercised end-to-end here.
 */
final class InterventionRepositoryOwnershipFilterTest extends TestCase
{
    public function testHseBypassesAddsNoFilterAtAll(): void
    {
        $currentUser = $this->buildUser(null);
        $queryBuilder = $this->buildQueryBuilder();
        $dqlBefore = $queryBuilder->getDQL();

        $this->invokeApplyOwnershipFilter($queryBuilder, $currentUser, true);

        $this->assertSame($dqlBefore, $queryBuilder->getDQL());
    }

    public function testPrestataireSideUserWithEntrepriseIsScopedByCreatorEntrepriseNotChefProjet(): void
    {
        $entreprise = $this->buildEntreprise();
        $currentUser = $this->buildUser($entreprise);
        $queryBuilder = $this->buildQueryBuilder();

        $this->invokeApplyOwnershipFilter($queryBuilder, $currentUser, false);

        $dql = $queryBuilder->getDQL();
        $this->assertStringContainsString('pt.createdBy', $dql);
        $this->assertStringContainsString('ptCreator.entreprise = :entreprise', $dql);
        $this->assertStringNotContainsString('pp.chefProjet', $dql);
        $this->assertSame($entreprise, $queryBuilder->getParameter('entreprise')?->getValue());
    }

    public function testToaChefProjetWithoutEntrepriseKeepsLegacyChefProjetScope(): void
    {
        $currentUser = $this->buildUser(null);
        $queryBuilder = $this->buildQueryBuilder();

        $this->invokeApplyOwnershipFilter($queryBuilder, $currentUser, false);

        $dql = $queryBuilder->getDQL();
        $this->assertStringContainsString('pp.chefProjet = :currentUser', $dql);
        $this->assertStringNotContainsString('ptCreator', $dql);
        $this->assertSame($currentUser, $queryBuilder->getParameter('currentUser')?->getValue());
    }

    private function invokeApplyOwnershipFilter(QueryBuilder $qb, User $currentUser, bool $isHse): void
    {
        $repository = (new \ReflectionClass(InterventionRepository::class))->newInstanceWithoutConstructor();

        $method = new \ReflectionMethod(InterventionRepository::class, 'applyOwnershipFilter');
        $method->setAccessible(true);
        $method->invoke($repository, $qb, $currentUser, $isHse);
    }

    private function buildQueryBuilder(): QueryBuilder
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getExpressionBuilder')->willReturn(new Expr());

        $qb = new QueryBuilder($entityManager);
        $qb->select('i.id as id')
            ->from(Intervention::class, 'i')
            ->leftJoin('i.permitTravail', 'pt')
            ->leftJoin('pt.planPrevention', 'pp');

        return $qb;
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

    private function buildUser(?Entreprise $entreprise): User
    {
        $user = new User();
        $user->setEmail('user-' . uniqid() . '@example.test');
        $user->setRoles(['ROLE_CHEF_PROJET_PRESTATAIRE']);
        $user->setEntreprise($entreprise);

        $reflection = new \ReflectionProperty(User::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($user, 1);

        return $user;
    }
}
