<?php

declare(strict_types=1);

namespace App\Tests\Unit\Api\Processor;

use ApiPlatform\Metadata\Patch;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Processor\SuiviJournalierUpdateProcessor;
use App\Domain\Intervention\Entity\Intervention;
use App\Domain\Intervention\Entity\SuiviJournalier;
use App\Domain\Intervention\Enum\StatutIntervention;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

class SuiviJournalierUpdateProcessorTest extends TestCase
{
    private ProcessorInterface&MockObject $persistProcessor;
    private SuiviJournalierUpdateProcessor $processor;

    protected function setUp(): void
    {
        $this->persistProcessor = $this->createMock(ProcessorInterface::class);
        $this->persistProcessor->method('process')->willReturnArgument(0);

        $this->processor = new SuiviJournalierUpdateProcessor($this->persistProcessor);
    }

    public function testUpdatingSuiviFromZeroToPositiveAvancementMovesEvaluationCompleteInterventionToEnCours(): void
    {
        $intervention = $this->buildIntervention(StatutIntervention::EVALUATION_COMPLETE);
        $suivi = $this->buildSuivi($intervention, '2026-01-01', 15);

        $result = $this->processor->process($suivi, new Patch());

        $this->assertSame($suivi, $result);
        $this->assertSame(StatutIntervention::EN_COURS, $intervention->getStatut());
    }

    public function testUpdatingSuiviKeptAtZeroAvancementDoesNotMoveEvaluationCompleteIntervention(): void
    {
        $intervention = $this->buildIntervention(StatutIntervention::EVALUATION_COMPLETE);
        $suivi = $this->buildSuivi($intervention, '2026-01-01', 0);
        $suivi->setRealise(false);
        $suivi->setMotifNonRealisation('Intemperies');

        $result = $this->processor->process($suivi, new Patch());

        $this->assertSame($suivi, $result);
        $this->assertSame(StatutIntervention::EVALUATION_COMPLETE, $intervention->getStatut());
    }

    public function testUpdatingSuiviOnAlreadyEnCoursInterventionNeverRegressesStatut(): void
    {
        // No matter the avancement value, an intervention already EN_COURS must
        // stay EN_COURS: this is a one-way transition, not a toggle.
        $intervention = $this->buildIntervention(StatutIntervention::EN_COURS);
        $suivi = $this->buildSuivi($intervention, '2026-01-02', 0);

        $result = $this->processor->process($suivi, new Patch());

        $this->assertSame($suivi, $result);
        $this->assertSame(StatutIntervention::EN_COURS, $intervention->getStatut());
    }

    public function testUpdatingSuiviOnAlreadyEnCoursInterventionWithPositiveAvancementStaysEnCours(): void
    {
        $intervention = $this->buildIntervention(StatutIntervention::EN_COURS);
        $suivi = $this->buildSuivi($intervention, '2026-01-03', 42);

        $result = $this->processor->process($suivi, new Patch());

        $this->assertSame($suivi, $result);
        $this->assertSame(StatutIntervention::EN_COURS, $intervention->getStatut());
    }

    public function testUpdatingSuiviWithoutMotifWhileNonRealiseStillFailsBeforeStatutLogic(): void
    {
        $intervention = $this->buildIntervention(StatutIntervention::EVALUATION_COMPLETE);
        $suivi = $this->buildSuivi($intervention, '2026-01-01', 0);
        $suivi->setRealise(false);
        $suivi->setMotifNonRealisation('');

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('suivi_journalier.motif_obligatoire');

        try {
            $this->processor->process($suivi, new Patch());
        } finally {
            $this->assertSame(StatutIntervention::EVALUATION_COMPLETE, $intervention->getStatut());
        }
    }

    public function testUpdatingSuiviWithInvalidAvancementStillFailsBeforeStatutLogic(): void
    {
        $intervention = $this->buildIntervention(StatutIntervention::EVALUATION_COMPLETE);
        $suivi = $this->buildSuivi($intervention, '2026-01-01', 15);

        $reflection = new \ReflectionProperty(SuiviJournalier::class, 'avancementPourcentage');
        $reflection->setAccessible(true);
        $reflection->setValue($suivi, 150);

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('suivi_journalier.avancement_invalide');

        try {
            $this->processor->process($suivi, new Patch());
        } finally {
            $this->assertSame(StatutIntervention::EVALUATION_COMPLETE, $intervention->getStatut());
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

    private function buildSuivi(Intervention $intervention, string $date, int $avancement): SuiviJournalier
    {
        $suivi = new SuiviJournalier();
        $suivi->setIntervention($intervention);
        $suivi->setDate(new \DateTimeImmutable($date));
        $suivi->setNomResponsable('Jean Rakoto');
        $suivi->setRealise(true);
        $suivi->setAvancementPourcentage($avancement);

        return $suivi;
    }
}
