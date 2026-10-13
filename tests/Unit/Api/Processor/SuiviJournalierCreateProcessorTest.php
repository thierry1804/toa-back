<?php

declare(strict_types=1);

namespace App\Tests\Unit\Api\Processor;

use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Processor\SuiviJournalierCreateProcessor;
use App\Domain\Intervention\Entity\Intervention;
use App\Domain\Intervention\Entity\SuiviJournalier;
use App\Domain\Intervention\Enum\StatutIntervention;
use App\Domain\Intervention\Repository\SuiviJournalierRepository;
use App\Domain\User\Entity\User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Uid\Uuid;

class SuiviJournalierCreateProcessorTest extends TestCase
{
    private ProcessorInterface&MockObject $persistProcessor;
    private TokenStorageInterface&MockObject $tokenStorage;
    private SuiviJournalierRepository&MockObject $suiviRepository;
    private SuiviJournalierCreateProcessor $processor;

    protected function setUp(): void
    {
        $this->persistProcessor = $this->createMock(ProcessorInterface::class);
        $this->persistProcessor->method('process')->willReturnArgument(0);

        $this->tokenStorage   = $this->createMock(TokenStorageInterface::class);
        $this->suiviRepository = $this->createMock(SuiviJournalierRepository::class);

        $this->processor = new SuiviJournalierCreateProcessor(
            $this->persistProcessor,
            $this->tokenStorage,
            $this->suiviRepository,
        );
    }

    public function testCreatingSuiviOnEvaluationCompleteInterventionMovesStatutToEnCours(): void
    {
        $intervention = $this->buildIntervention(StatutIntervention::EVALUATION_COMPLETE);
        $this->setActor($this->buildUser());

        $this->suiviRepository->method('existsByInterventionAndDate')->willReturn(false);

        $suivi = $this->buildSuivi($intervention, '2026-01-01');

        $result = $this->processor->process($suivi, new Post());

        $this->assertSame($suivi, $result);
        $this->assertSame(StatutIntervention::EN_COURS, $intervention->getStatut());
    }

    public function testCreatingSecondSuiviOnEnCoursInterventionSucceedsAndDoesNotRegressStatut(): void
    {
        // A field agent must be able to log a suivi journalier every day for
        // the whole duration of the intervention, not just once right after
        // the evaluation. EN_COURS must therefore be accepted too, and the
        // statut must not move backwards nor be otherwise disturbed.
        $intervention = $this->buildIntervention(StatutIntervention::EN_COURS);
        $this->setActor($this->buildUser());

        $this->suiviRepository->method('existsByInterventionAndDate')->willReturn(false);

        $suivi = $this->buildSuivi($intervention, '2026-01-02');

        $result = $this->processor->process($suivi, new Post());

        $this->assertSame($suivi, $result);
        $this->assertSame(StatutIntervention::EN_COURS, $intervention->getStatut());
    }

    public function testCreatingSuiviOnEnPreparationInterventionIsStillRejected(): void
    {
        $intervention = $this->buildIntervention(StatutIntervention::EN_PREPARATION);
        $this->setActor($this->buildUser());

        $this->suiviRepository->expects($this->never())->method('existsByInterventionAndDate');

        $suivi = $this->buildSuivi($intervention, '2026-01-01');

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('suivi_journalier.evaluation_requise');

        try {
            $this->processor->process($suivi, new Post());
        } finally {
            $this->assertSame(StatutIntervention::EN_PREPARATION, $intervention->getStatut());
        }
    }

    public function testCreatingSuiviOnTermineeInterventionIsRejected(): void
    {
        $intervention = $this->buildIntervention(StatutIntervention::TERMINEE);
        $this->setActor($this->buildUser());

        $this->suiviRepository->expects($this->never())->method('existsByInterventionAndDate');

        $suivi = $this->buildSuivi($intervention, '2026-01-01');

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('suivi_journalier.evaluation_requise');

        try {
            $this->processor->process($suivi, new Post());
        } finally {
            $this->assertSame(StatutIntervention::TERMINEE, $intervention->getStatut());
        }
    }

    private function buildIntervention(StatutIntervention $statut): Intervention
    {
        $intervention = new Intervention();
        $intervention->setStatut($statut);

        $reflection = new \ReflectionProperty(Intervention::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($intervention, Uuid::v7());

        return $intervention;
    }

    private function buildSuivi(Intervention $intervention, string $date): SuiviJournalier
    {
        $suivi = new SuiviJournalier();
        $suivi->setIntervention($intervention);
        $suivi->setDate(new \DateTimeImmutable($date));
        $suivi->setNomResponsable('Jean Rakoto');
        $suivi->setRealise(true);
        $suivi->setAvancementPourcentage(10);

        return $suivi;
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
