<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\PlanPrevention\Service;

use App\Domain\PlanPrevention\Entity\ModeOperatoirePlanPrevention;
use App\Domain\PlanPrevention\Entity\PhasePlanPrevention;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Entity\RisquePrevention;
use App\Domain\PlanPrevention\Service\PlanPreventionSectionsSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

class PlanPreventionSectionsSynchronizerTest extends TestCase
{
    private PlanPreventionSectionsSynchronizer $synchronizer;
    private UnitOfWork&MockObject $unitOfWork;

    protected function setUp(): void
    {
        $this->unitOfWork = $this->createMock(UnitOfWork::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getUnitOfWork')->willReturn($this->unitOfWork);

        $this->synchronizer = new PlanPreventionSectionsSynchronizer($entityManager);
    }

    public function testModeMovedToNewPhaseIsKeptAndItsOrphanRemovalCancelled(): void
    {
        $plan = new PlanPrevention();
        $movedMode = $this->mode('Mode déplacé');
        $oldPhase = $this->phase('Ancienne phase', [$movedMode]);
        $plan->addSection($oldPhase);

        $this->unitOfWork->expects($this->once())->method('cancelOrphanRemoval')->with($movedMode);

        $this->synchronizer->sync($plan, [
            ['id' => (string) $oldPhase->getId(), 'libelle' => 'Ancienne phase', 'taches' => []],
            ['libelle' => 'Nouvelle phase', 'taches' => [['id' => (string) $movedMode->getId(), 'tache' => 'Mode déplacé']]],
        ]);

        $this->assertCount(2, $plan->getSections());
        $newPhase = $plan->getSections()->last();
        $this->assertSame('Nouvelle phase', $newPhase->getLibelle());
        $this->assertCount(0, $oldPhase->getModesOperatoires());
        $this->assertCount(1, $newPhase->getModesOperatoires());
        $this->assertSame($movedMode, $newPhase->getModesOperatoires()->first());
        $this->assertSame($newPhase, $movedMode->getPhase());
    }

    public function testDoesNotCancelOrphanRemovalWhenNoModeMoves(): void
    {
        $plan = new PlanPrevention();
        $mode = $this->mode('Mode');
        $phase = $this->phase('Phase', [$mode]);
        $plan->addSection($phase);

        $this->unitOfWork->expects($this->never())->method('cancelOrphanRemoval');

        $this->synchronizer->sync($plan, [
            ['id' => (string) $phase->getId(), 'libelle' => 'Phase', 'taches' => [['id' => (string) $mode->getId(), 'tache' => 'Mode']]],
        ]);
    }

    public function testCreatesPhasesAndModesOperatoires(): void
    {
        $plan = new PlanPrevention();

        $this->synchronizer->sync($plan, [
            [
                'libelle' => 'Phase 1',
                'taches' => [
                    ['tache' => 'TERRASSEMENT DU SITE', 'materiel' => 'Pelle', 'qui' => 'Equipe GC'],
                    ['tache' => 'REMBLAYAGE'],
                ],
            ],
            ['libelle' => 'Phase 2'],
        ]);

        $this->assertCount(2, $plan->getSections());
        $phase1 = $plan->getSections()->first();
        $this->assertSame('Phase 1', $phase1->getLibelle());
        $this->assertSame(0, $phase1->getOrdre());
        $this->assertCount(2, $phase1->getModesOperatoires());
        $this->assertSame('Equipe GC', $phase1->getModesOperatoires()->first()->getQui());
        $this->assertSame(1, $phase1->getModesOperatoires()->last()->getOrdre());
    }

    public function testKeepsExistingIdsAndRemovesMissingItems(): void
    {
        $plan = new PlanPrevention();
        $keptMode = $this->mode('Mode conservé');
        $removedMode = $this->mode('Mode supprimé');
        $phase = $this->phase('Phase 1', [$keptMode, $removedMode]);
        $removedPhase = $this->phase('Phase à supprimer', []);
        $plan->addSection($phase);
        $plan->addSection($removedPhase);

        $risque = (new RisquePrevention())->setTachePlanifieeId((string) $removedMode->getId());
        $plan->addRisque($risque);

        $this->synchronizer->sync($plan, [
            [
                'id' => (string) $phase->getId(),
                'libelle' => 'Phase 1 renommée',
                'taches' => [['id' => (string) $keptMode->getId(), 'tache' => 'Mode modifié']],
            ],
        ]);

        $this->assertCount(1, $plan->getSections());
        $this->assertSame($phase, $plan->getSections()->first());
        $this->assertSame('Phase 1 renommée', $phase->getLibelle());
        $this->assertCount(1, $phase->getModesOperatoires());
        $this->assertSame($keptMode, $phase->getModesOperatoires()->first());
        $this->assertSame('Mode modifié', $keptMode->getTache());
        $this->assertNull($risque->getTachePlanifieeId());
    }

    public function testRejectsUnknownIdentifiers(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('section_introuvable');

        $this->synchronizer->sync(new PlanPrevention(), [['id' => Uuid::v4()->toRfc4122(), 'libelle' => 'Phase']]);
    }

    public function testRejectsBlankLabel(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('libelle_required');

        $this->synchronizer->sync(new PlanPrevention(), [['libelle' => '  ']]);
    }

    public function testRejectsNonListPayload(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('sections_invalid');

        $this->synchronizer->sync(new PlanPrevention(), ['a' => ['libelle' => 'Phase']]);
    }

    /** @param list<ModeOperatoirePlanPrevention> $modes */
    private function phase(string $libelle, array $modes): PhasePlanPrevention
    {
        $phase = (new PhasePlanPrevention())->setLibelle($libelle);
        $this->setId($phase);
        foreach ($modes as $mode) {
            $phase->addModeOperatoire($mode);
        }

        return $phase;
    }

    private function mode(string $tache): ModeOperatoirePlanPrevention
    {
        $mode = (new ModeOperatoirePlanPrevention())->setTache($tache);
        $this->setId($mode);

        return $mode;
    }

    private function setId(object $entity): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setValue($entity, Uuid::v4());
    }
}
