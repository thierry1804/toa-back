<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Entreprise\Service;

use App\Domain\Entreprise\Entity\SousTraitant;
use App\Domain\Entreprise\Service\AgentProjetScope;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class AgentProjetScopeTest extends TestCase
{
    public function testOnlyAnAgentAttachedToASubcontractorIsRestricted(): void
    {
        $scope = $this->scope(0);
        $attached = (new User())->setRoles(['ROLE_AGENT_TERRAIN'])->setSousTraitant(new SousTraitant());
        $legacy = (new User())->setRoles(['ROLE_AGENT_TERRAIN']);
        $prestataire = (new User())->setRoles(['ROLE_PRESTATAIRE'])->setSousTraitant(new SousTraitant());

        $this->assertTrue($scope->restricts($attached));
        $this->assertFalse($scope->restricts($legacy));
        $this->assertFalse($scope->restricts($prestataire));
        $this->assertTrue($scope->allows($prestataire, null));
        $this->assertTrue($scope->allows($legacy, 4));
        $this->assertFalse($scope->allows($attached, null));
    }

    public function testValidationMailFollowsThePlanificationAssignment(): void
    {
        $scope = $this->scope(0);
        $legacy = (new User())->setRoles(['ROLE_AGENT_TERRAIN']);
        $attached = (new User())->setRoles(['ROLE_AGENT_TERRAIN'])->setSousTraitant(new SousTraitant());

        $this->assertFalse($scope->includeInValidationMail($legacy, null));
        $this->assertFalse($scope->includeInValidationMail($attached, null));
        $this->assertTrue($scope->includeInValidationMail($legacy, 9));
        $this->assertFalse($this->scope(0)->includeInValidationMail($attached, 9));
        $this->assertTrue($this->scope(1)->includeInValidationMail($attached, 9));
    }

    private function scope(int $assignedCount): AgentProjetScope
    {
        return new class($this->createMock(Security::class), $this->createMock(EntityManagerInterface::class), $assignedCount) extends AgentProjetScope {
            public function __construct(
                Security $security,
                EntityManagerInterface $entityManager,
                private readonly int $assignedCount,
            ) {
                parent::__construct($security, $entityManager);
            }

            public function isAssigned(int $planificationId, SousTraitant $sousTraitant): bool
            {
                return $this->assignedCount > 0;
            }
        };
    }
}
