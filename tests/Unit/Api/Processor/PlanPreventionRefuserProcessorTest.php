<?php

declare(strict_types=1);

namespace App\Tests\Unit\Api\Processor;

use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Processor\PlanPreventionRefuserProcessor;
use App\Domain\PlanPrevention\Entity\DecisionHsePlanPrevention;
use App\Domain\PlanPrevention\Entity\DocumentPrevention;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Enum\DecisionHse;
use App\Domain\PlanPrevention\Enum\StatutPlanPrevention;
use App\Domain\PlanPrevention\Enum\TypeDocumentPrevention;
use App\Domain\PlanPrevention\Service\PlanPreventionNotificationService;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Uid\Uuid;

class PlanPreventionRefuserProcessorTest extends TestCase
{
    private ProcessorInterface&MockObject $persistProcessor;
    private EntityManagerInterface&MockObject $entityManager;
    private PlanPreventionNotificationService&MockObject $notificationService;
    private RequestStack $requestStack;
    private TokenStorageInterface&MockObject $tokenStorage;
    private PlanPreventionRefuserProcessor $processor;
    private User $user;

    protected function setUp(): void
    {
        $this->persistProcessor = $this->createMock(ProcessorInterface::class);
        $this->persistProcessor->method('process')->willReturnArgument(0);

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->notificationService = $this->createMock(PlanPreventionNotificationService::class);
        $this->requestStack = new RequestStack();
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);

        $this->user = (new User())->setEmail('hse@toa.mg');
        $idProp = new \ReflectionProperty(User::class, 'id');
        $idProp->setAccessible(true);
        $idProp->setValue($this->user, 7);

        $this->tokenStorage->method('getToken')->willReturn(
            new UsernamePasswordToken($this->user, 'main', ['ROLE_HSE'])
        );

        $this->processor = new PlanPreventionRefuserProcessor(
            $this->persistProcessor,
            $this->entityManager,
            $this->tokenStorage,
            $this->notificationService,
            $this->requestStack,
        );
    }

    public function testRefuseFromSoumisSetsACorrigerAndNotifies(): void
    {
        $plan = $this->planWithId(StatutPlanPrevention::SOUMIS);
        $this->pushCommentaire('Motif chef');

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(static function (mixed $decision): bool {
                return $decision instanceof DecisionHsePlanPrevention
                    && $decision->getDecision() === DecisionHse::REFUSE
                    && $decision->getCommentaire() === 'Motif chef';
            }));
        $this->notificationService->expects($this->once())
            ->method('notifierPrestataire')
            ->with($plan, 'Motif chef');

        $this->processor->process($plan, new Post());

        $this->assertSame(StatutPlanPrevention::A_CORRIGER, $plan->getStatut());
    }

    /**
     * R-07 : le refus ne doit plus jamais être bloqué par des pièces non consultées.
     */
    public function testRefuseSucceedsEvenWithoutAnyDocumentConsulted(): void
    {
        $plan = $this->planWithId(StatutPlanPrevention::SOUMIS);
        $plan->addDocument((new DocumentPrevention())
            ->setType(TypeDocumentPrevention::FDS)
            ->setFilePath('plans-prevention/x/FDS.pdf')
            ->setMimeType('application/pdf')
            ->setUploadedAt(new \DateTimeImmutable()));
        $this->pushCommentaire('Motif');

        $this->processor->process($plan, new Post());

        $this->assertSame(StatutPlanPrevention::A_CORRIGER, $plan->getStatut());
        $this->assertNull($plan->getDocuments()->first()->getConsultedAt());
    }

    public function testRefuseFromExamineSetsACorriger(): void
    {
        $plan = $this->planWithId(StatutPlanPrevention::EXAMINE);
        $this->pushCommentaire('Motif HSE');

        $this->notificationService->expects($this->once())->method('notifierPrestataire');

        $this->processor->process($plan, new Post());

        $this->assertSame(StatutPlanPrevention::A_CORRIGER, $plan->getStatut());
    }

    public function testRefuseFromEnCoursDeValidationSetsACorriger(): void
    {
        $plan = $this->planWithId(StatutPlanPrevention::EN_COURS_DE_VALIDATION);
        $this->pushCommentaire('Motif');

        $this->processor->process($plan, new Post());

        $this->assertSame(StatutPlanPrevention::A_CORRIGER, $plan->getStatut());
    }

    public function testRefuseRequiresCommentaire(): void
    {
        $plan = $this->planWithId(StatutPlanPrevention::SOUMIS);
        $this->requestStack->push(new Request([], [], [], [], [], [], '{}'));

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('Commentaire obligatoire en cas de refus');

        $this->processor->process($plan, new Post());
    }

    public function testRefuseRejectsBrouillon(): void
    {
        $plan = $this->planWithId(StatutPlanPrevention::BROUILLON);
        $this->pushCommentaire('Motif');

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('plan_prevention.statut_invalide_pour_refus');

        $this->processor->process($plan, new Post());
    }

    private function pushCommentaire(string $commentaire): void
    {
        $this->requestStack->push(new Request(
            [],
            [],
            [],
            [],
            [],
            [],
            json_encode(['commentaire' => $commentaire], JSON_THROW_ON_ERROR),
        ));
    }

    private function planWithId(StatutPlanPrevention $statut): PlanPrevention
    {
        $plan = (new PlanPrevention())->setStatut($statut);
        $idProp = new \ReflectionProperty(PlanPrevention::class, 'id');
        $idProp->setAccessible(true);
        $idProp->setValue($plan, Uuid::v7());

        return $plan;
    }
}
