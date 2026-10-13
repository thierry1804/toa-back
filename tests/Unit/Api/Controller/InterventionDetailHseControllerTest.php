<?php

declare(strict_types=1);

namespace App\Tests\Unit\Api\Controller;

use App\Api\Controller\InterventionDetailHseController;
use App\Domain\Intervention\Entity\Intervention;
use App\Domain\Intervention\Entity\SuiviJournalier;
use App\Domain\Intervention\Enum\StatutIntervention;
use App\Domain\Intervention\Repository\InterventionRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Uid\Uuid;

/**
 * Covers Bug B3: the HSE detail endpoint must expose the avancement of the
 * MOST RECENT suivi journalier (by date), not an average of all suivis, even
 * when the hydrated Collection returned by Intervention::getSuivis() is not
 * ordered by date.
 */
class InterventionDetailHseControllerTest extends TestCase
{
    public function testAvancementMoyenIsTheMostRecentSuiviEvenWhenCollectionOrderIsNotSortedByDate(): void
    {
        $intervention = $this->buildIntervention(StatutIntervention::EN_COURS);

        // Deliberately inserted out of chronological order.
        $this->addSuivi($intervention, '2026-01-05', 21);
        $this->addSuivi($intervention, '2026-01-10', 60);
        $this->addSuivi($intervention, '2026-01-02', 2);

        $payload = $this->invokeController($intervention);

        $this->assertSame(60, $payload['avancementMoyen']);
    }

    public function testAvancementMoyenPicksLatestDateNotLastInsertedRow(): void
    {
        $intervention = $this->buildIntervention(StatutIntervention::EN_COURS);

        // The last suivi added here is NOT the most recent by date: this would
        // fail if the code picked suivis[count-1] instead of sorting by date.
        $this->addSuivi($intervention, '2026-02-01', 50);
        $this->addSuivi($intervention, '2026-01-01', 99);

        $payload = $this->invokeController($intervention);

        $this->assertSame(50, $payload['avancementMoyen']);
    }

    public function testAvancementMoyenIsZeroWhenThereAreNoSuivisAtAll(): void
    {
        $intervention = $this->buildIntervention(StatutIntervention::EVALUATION_COMPLETE);

        $payload = $this->invokeController($intervention);

        $this->assertSame(0, $payload['avancementMoyen']);
    }

    public function testAvancementMoyenWithASingleSuivi(): void
    {
        $intervention = $this->buildIntervention(StatutIntervention::EN_COURS);
        $this->addSuivi($intervention, '2026-03-01', 77);

        $payload = $this->invokeController($intervention);

        $this->assertSame(77, $payload['avancementMoyen']);
    }

    /**
     * @return array<string, mixed>
     */
    private function invokeController(Intervention $intervention): array
    {
        $repository = $this->createMock(InterventionRepository::class);
        $repository->method('findForHseDetail')->willReturn($intervention);

        $controller = new InterventionDetailHseController($repository);
        // Empty container: AbstractController::json() falls back to a plain
        // json_encode() when no 'serializer' service is registered.
        $controller->setContainer(new Container());

        $response = $controller->__invoke((string) $intervention->getId());

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        return $payload;
    }

    private function buildIntervention(StatutIntervention $statut): Intervention
    {
        $intervention = new Intervention();
        $intervention->setStatut($statut);

        $idProperty = new \ReflectionProperty(Intervention::class, 'id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($intervention, Uuid::v7());

        $createdAtProperty = new \ReflectionProperty(Intervention::class, 'createdAt');
        $createdAtProperty->setAccessible(true);
        $createdAtProperty->setValue($intervention, new \DateTimeImmutable('2026-01-01'));

        return $intervention;
    }

    private function addSuivi(Intervention $intervention, string $date, int $avancement): void
    {
        $suivi = new SuiviJournalier();
        $suivi->setIntervention($intervention);
        $suivi->setDate(new \DateTimeImmutable($date));
        $suivi->setNomResponsable('Jean Rakoto');
        $suivi->setRealise(true);
        $suivi->setAvancementPourcentage($avancement);

        $idProperty = new \ReflectionProperty(SuiviJournalier::class, 'id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($suivi, Uuid::v7());

        $suivisProperty = new \ReflectionProperty(Intervention::class, 'suivis');
        $suivisProperty->setAccessible(true);
        $suivisProperty->getValue($intervention)->add($suivi);
    }
}
