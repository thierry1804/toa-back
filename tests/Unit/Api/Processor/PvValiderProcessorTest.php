<?php

declare(strict_types=1);

namespace App\Tests\Unit\Api\Processor;

use ApiPlatform\Metadata\Post;
use App\Api\Processor\PvValiderProcessor;
use App\Domain\Intervention\Entity\Intervention;
use App\Domain\Intervention\Enum\StatutIntervention;
use App\Domain\PermitTravail\Entity\DecisionCdpPvReception;
use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Entity\PvReceptionPdf;
use App\Domain\PermitTravail\Enum\StatutPermitTravail;
use App\Domain\PermitTravail\Enum\StatutPvReceptionPdf;
use App\Domain\PermitTravail\Repository\PvReceptionPdfRepository;
use App\Domain\PermitTravail\Service\PermitTravailNotificationService;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Uid\Uuid;

class PvValiderProcessorTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private TokenStorageInterface&MockObject $tokenStorage;
    private PvReceptionPdfRepository&MockObject $pvRepository;
    private MessageBusInterface&MockObject $bus;
    private PermitTravailNotificationService&MockObject $notificationService;
    private PvValiderProcessor $processor;

    protected function setUp(): void
    {
        $this->entityManager       = $this->createMock(EntityManagerInterface::class);
        $this->tokenStorage        = $this->createMock(TokenStorageInterface::class);
        $this->pvRepository        = $this->createMock(PvReceptionPdfRepository::class);
        $this->bus                 = $this->createMock(MessageBusInterface::class);
        $this->notificationService = $this->createMock(PermitTravailNotificationService::class);

        $this->bus->method('dispatch')->willReturnCallback(
            static fn (object $message): Envelope => new Envelope($message),
        );

        $this->processor = new PvValiderProcessor(
            $this->entityManager,
            $this->tokenStorage,
            $this->pvRepository,
            $this->bus,
            $this->notificationService,
        );
    }

    public function testValidationReussieTermineIntervention(): void
    {
        $permit       = $this->buildPermit(StatutPermitTravail::CLOTURE);
        $intervention = $this->buildIntervention(StatutIntervention::EN_COURS, $permit);
        $pv           = $this->buildPv($permit, StatutPvReceptionPdf::GENERE);

        $this->pvRepository->method('findByPermitTravailId')->willReturn($pv);
        $this->mockInterventionRepository($intervention);
        $this->mockCounts(suiviCount: 1, evalCount: 1);
        $this->setActor($this->buildUser());

        $this->entityManager->expects($this->once())->method('persist')
            ->with($this->isInstanceOf(DecisionCdpPvReception::class));
        $this->entityManager->expects($this->once())->method('flush');
        $this->notificationService->expects($this->once())->method('notifierHseArchivage');

        $result = $this->processor->process($permit, new Post());

        $this->assertSame($permit, $result);
        $this->assertSame(StatutPermitTravail::PV_VALIDE, $permit->getStatut());
        $this->assertSame(StatutIntervention::TERMINEE, $intervention->getStatut());
    }

    public function testPermitNotClotureThrowsAndLeavesInterventionUntouched(): void
    {
        $permit       = $this->buildPermit(StatutPermitTravail::VALIDE_HSE);
        $intervention = $this->buildIntervention(StatutIntervention::EN_COURS, $permit);

        // Reached before any repository lookup: these must never be called.
        $this->pvRepository->expects($this->never())->method('findByPermitTravailId');
        $this->entityManager->expects($this->never())->method('getRepository');
        $this->entityManager->expects($this->never())->method('flush');

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('permit_travail.not_cloture');

        try {
            $this->processor->process($permit, new Post());
        } finally {
            $this->assertSame(StatutIntervention::EN_COURS, $intervention->getStatut());
        }
    }

    public function testPvNotGenereThrowsAndLeavesInterventionUntouched(): void
    {
        $permit       = $this->buildPermit(StatutPermitTravail::CLOTURE);
        $intervention = $this->buildIntervention(StatutIntervention::EN_COURS, $permit);
        $pv           = $this->buildPv($permit, StatutPvReceptionPdf::EN_COURS);

        $this->pvRepository->method('findByPermitTravailId')->willReturn($pv);
        $this->entityManager->expects($this->never())->method('getRepository');
        $this->entityManager->expects($this->never())->method('flush');

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('permit_travail.pv_not_genere');

        try {
            $this->processor->process($permit, new Post());
        } finally {
            $this->assertSame(StatutIntervention::EN_COURS, $intervention->getStatut());
        }
    }

    public function testMissingSuiviThrowsAndLeavesInterventionUntouched(): void
    {
        $permit       = $this->buildPermit(StatutPermitTravail::CLOTURE);
        $intervention = $this->buildIntervention(StatutIntervention::EN_COURS, $permit);
        $pv           = $this->buildPv($permit, StatutPvReceptionPdf::GENERE);

        $this->pvRepository->method('findByPermitTravailId')->willReturn($pv);
        $this->mockInterventionRepository($intervention);
        $this->mockCounts(suiviCount: 0, evalCount: 1);
        $this->entityManager->expects($this->never())->method('flush');

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('permit_travail.suivi_journalier_manquant');

        try {
            $this->processor->process($permit, new Post());
        } finally {
            $this->assertSame(StatutIntervention::EN_COURS, $intervention->getStatut());
        }
    }

    public function testMissingEvaluationThrowsAndLeavesInterventionUntouched(): void
    {
        $permit       = $this->buildPermit(StatutPermitTravail::CLOTURE);
        $intervention = $this->buildIntervention(StatutIntervention::EN_COURS, $permit);
        $pv           = $this->buildPv($permit, StatutPvReceptionPdf::GENERE);

        $this->pvRepository->method('findByPermitTravailId')->willReturn($pv);
        $this->mockInterventionRepository($intervention);
        $this->mockCounts(suiviCount: 1, evalCount: 0);
        $this->entityManager->expects($this->never())->method('flush');

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('permit_travail.evaluation_risque_manquante');

        try {
            $this->processor->process($permit, new Post());
        } finally {
            $this->assertSame(StatutIntervention::EN_COURS, $intervention->getStatut());
        }
    }

    private function mockInterventionRepository(Intervention $intervention): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($intervention);

        $this->entityManager->method('getRepository')
            ->with(Intervention::class)
            ->willReturn($repository);
    }

    private function mockCounts(int $suiviCount, int $evalCount): void
    {
        $suiviQuery = $this->createMock(Query::class);
        $suiviQuery->method('setParameter')->willReturnSelf();
        $suiviQuery->method('getSingleScalarResult')->willReturn($suiviCount);

        $evalQuery = $this->createMock(Query::class);
        $evalQuery->method('setParameter')->willReturnSelf();
        $evalQuery->method('getSingleScalarResult')->willReturn($evalCount);

        $this->entityManager->method('createQuery')->willReturnCallback(
            function (string $dql) use ($suiviQuery, $evalQuery): Query {
                if (str_contains($dql, 'SuiviJournalier')) {
                    return $suiviQuery;
                }

                return $evalQuery;
            },
        );
    }

    private function buildPermit(StatutPermitTravail $statut): PermitTravail
    {
        $permit = new PermitTravail();
        $permit->setStatut($statut);

        $reflection = new \ReflectionProperty(PermitTravail::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($permit, Uuid::v7());

        return $permit;
    }

    private function buildIntervention(StatutIntervention $statut, PermitTravail $permit): Intervention
    {
        $intervention = new Intervention();
        $intervention->setStatut($statut);
        $intervention->setPermitTravail($permit);

        $reflection = new \ReflectionProperty(Intervention::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($intervention, Uuid::v7());

        return $intervention;
    }

    private function buildPv(PermitTravail $permit, StatutPvReceptionPdf $statut): PvReceptionPdf
    {
        $pv = new PvReceptionPdf();
        $pv->setPermitTravail($permit);
        $pv->setStatut($statut);

        $reflection = new \ReflectionProperty(PvReceptionPdf::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($pv, Uuid::v7());

        return $pv;
    }

    private function buildUser(): User
    {
        $user = new User();
        $user->setEmail('hse@toa.local');
        $user->setName('Hse');
        $user->setFirstname('Test');
        $user->setRoles(['ROLE_HSE']);

        return $user;
    }

    private function setActor(User $actor): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($actor);
        $this->tokenStorage->method('getToken')->willReturn($token);
    }
}
