<?php

declare(strict_types=1);

namespace App\Tests\Unit\Api\Processor;

use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Processor\PlanPreventionCreateProcessor;
use App\Domain\ActivityPlanning\Entity\ActivityPlanning;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Service\PlanPreventionSectionsSynchronizer;
use App\Domain\User\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class PlanPreventionCreateProcessorTest extends TestCase
{
    private ProcessorInterface&MockObject $persistProcessor;
    private EntityManagerInterface&MockObject $entityManager;
    private PlanPreventionCreateProcessor $processor;

    protected function setUp(): void
    {
        $this->persistProcessor = $this->createMock(ProcessorInterface::class);
        $this->persistProcessor->method('process')->willReturnArgument(0);

        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        // generateReference()/generateReferenceActivite() lisent l'année en cours via une requête SQL brute.
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(null);
        $this->entityManager->method('getConnection')->willReturn($connection);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn((new User())->setEmail('prestataire@acme.mg'));
        $tokenStorage->method('getToken')->willReturn($token);

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(Request::create('/api/plans-prevention', 'POST', content: '{}'));

        $this->processor = new PlanPreventionCreateProcessor(
            $this->persistProcessor,
            $this->entityManager,
            $tokenStorage,
            $requestStack,
            $this->createMock(PlanPreventionSectionsSynchronizer::class),
        );
    }

    private function planification(?User $createdBy): ActivityPlanning
    {
        $planification = (new ActivityPlanning())->setCreatedBy($createdBy);
        $ref = new \ReflectionProperty($planification, 'id');
        $ref->setAccessible(true);
        $ref->setValue($planification, 42);

        return $planification;
    }

    public function testChefProjetIsAutoAssignedFromThePlanificationsCreator(): void
    {
        $chefProjet = (new User())->setEmail('chef@toa.mg');
        $this->entityManager->method('find')->with(ActivityPlanning::class, 42)->willReturn($this->planification($chefProjet));

        $plan = (new PlanPrevention())
            ->setActivitePlanifiee('Test')
            ->setCodeSite('SITE1')
            ->setPlanificationId(42);

        $result = $this->processor->process($plan, new Post());

        $this->assertSame($chefProjet, $result->getChefProjet());
    }

    public function testExplicitlySuppliedChefProjetIsNotOverridden(): void
    {
        $planningCreator = (new User())->setEmail('chef.planning@toa.mg');
        $chosenChefProjet = (new User())->setEmail('chef.choisi@toa.mg');
        $this->entityManager->method('find')->with(ActivityPlanning::class, 42)->willReturn($this->planification($planningCreator));

        $plan = (new PlanPrevention())
            ->setActivitePlanifiee('Test')
            ->setCodeSite('SITE1')
            ->setPlanificationId(42)
            ->setChefProjet($chosenChefProjet);

        $result = $this->processor->process($plan, new Post());

        $this->assertSame($chosenChefProjet, $result->getChefProjet());
    }

    public function testNoChefProjetWhenThereIsNoLinkedPlanification(): void
    {
        $plan = (new PlanPrevention())->setActivitePlanifiee('Test')->setCodeSite('SITE1');

        $result = $this->processor->process($plan, new Post());

        $this->assertNull($result->getChefProjet());
    }
}
