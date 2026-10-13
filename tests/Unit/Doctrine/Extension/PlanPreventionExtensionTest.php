<?php

declare(strict_types=1);

namespace App\Tests\Unit\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use App\Domain\Menu\Service\PermissionChecker;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\User\Entity\User;
use App\Doctrine\Extension\PlanPreventionExtension;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class PlanPreventionExtensionTest extends TestCase
{
    public function testAgentTerrainOnlyReceivesPlansCreatedByThemWhenTheyHaveNoEntreprise(): void
    {
        $user = (new User())
            ->setEmail('agent@example.test')
            ->setRoles(['ROLE_AGENT_TERRAIN']);
        $security = $this->securityFor($user);
        $permissions = $this->createMock(PermissionChecker::class);
        $permissions->method('isGranted')->willReturn(false);
        $queryBuilder = $this->buildQueryBuilder();

        (new PlanPreventionExtension($security, $permissions))->applyToCollection(
            $queryBuilder,
            new QueryNameGenerator(),
            PlanPrevention::class,
        );

        $this->assertStringContainsString('e.createdBy', $queryBuilder->getDQL());
        $this->assertStringContainsString('pp_user.email = :pp_current_user', $queryBuilder->getDQL());
        $this->assertSame('agent@example.test', $queryBuilder->getParameter('pp_current_user')?->getValue());
    }

    public function testEditableProfileOnlyReceivesAssignedPlans(): void
    {
        $user = (new User())
            ->setEmail('chef@example.test')
            ->setRoles(['ROLE_CHEF_PROJET']);
        $security = $this->securityFor($user);
        $permissions = $this->createMock(PermissionChecker::class);
        $permissions->method('isGranted')->willReturn(true);
        $queryBuilder = $this->buildQueryBuilder();

        (new PlanPreventionExtension($security, $permissions))->applyToCollection(
            $queryBuilder,
            new QueryNameGenerator(),
            PlanPrevention::class,
        );

        $this->assertStringContainsString('e.chefProjet', $queryBuilder->getDQL());
        $this->assertStringContainsString('pp_chef.email = :pp_chef_email', $queryBuilder->getDQL());
        $this->assertSame('chef@example.test', $queryBuilder->getParameter('pp_chef_email')?->getValue());
    }

    private function securityFor(User $user): Security
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);
        $security->method('isGranted')->willReturn(false);

        return $security;
    }

    private function buildQueryBuilder(): QueryBuilder
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getExpressionBuilder')->willReturn(new Expr());
        $queryBuilder = new QueryBuilder($entityManager);
        $queryBuilder->select('e')->from(PlanPrevention::class, 'e');

        return $queryBuilder;
    }
}
