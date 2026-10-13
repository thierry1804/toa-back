<?php

declare(strict_types=1);

namespace App\Tests\Unit\Api\Controller;

use App\Api\Controller\InterventionDetailHseController;
use App\Domain\Entreprise\Entity\Entreprise;
use App\Domain\Intervention\Entity\Intervention;
use App\Domain\Intervention\Entity\SuiviJournalier;
use App\Domain\Intervention\Enum\StatutIntervention;
use App\Domain\Intervention\Repository\InterventionRepository;
use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Enum\ProcessusPermitTravail;
use App\Domain\PermitTravail\Enum\TypePermitTravail;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\User\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

/**
 * Covers Bug B3 (most-recent-suivi avancement) and, since this task, the
 * ownership/IDOR guard added to this controller: INTERVENTION_SUIVI_DETAIL
 * (the #[IsGranted] voter) only confirms the actor's role has the action
 * key at all, with no per-record check — before this fix, any authenticated
 * user holding that action key could read ANY intervention by id regardless
 * of entreprise/chefProjet.
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

        $payload = $this->invokeController($intervention, $this->bypassSecurity());

        $this->assertSame(60, $payload['avancementMoyen']);
    }

    public function testAvancementMoyenPicksLatestDateNotLastInsertedRow(): void
    {
        $intervention = $this->buildIntervention(StatutIntervention::EN_COURS);

        // The last suivi added here is NOT the most recent by date: this would
        // fail if the code picked suivis[count-1] instead of sorting by date.
        $this->addSuivi($intervention, '2026-02-01', 50);
        $this->addSuivi($intervention, '2026-01-01', 99);

        $payload = $this->invokeController($intervention, $this->bypassSecurity());

        $this->assertSame(50, $payload['avancementMoyen']);
    }

    public function testAvancementMoyenIsZeroWhenThereAreNoSuivisAtAll(): void
    {
        $intervention = $this->buildIntervention(StatutIntervention::EVALUATION_COMPLETE);

        $payload = $this->invokeController($intervention, $this->bypassSecurity());

        $this->assertSame(0, $payload['avancementMoyen']);
    }

    public function testAvancementMoyenWithASingleSuivi(): void
    {
        $intervention = $this->buildIntervention(StatutIntervention::EN_COURS);
        $this->addSuivi($intervention, '2026-03-01', 77);

        $payload = $this->invokeController($intervention, $this->bypassSecurity());

        $this->assertSame(77, $payload['avancementMoyen']);
    }

    public function testHseRoleCanSeeAnyInterventionRegardlessOfEntreprise(): void
    {
        $entreprise = $this->buildEntreprise();
        $otherEntreprise = $this->buildEntreprise();
        $creator = $this->buildUser($otherEntreprise);
        $intervention = $this->buildIntervention(StatutIntervention::EN_COURS, $creator);

        $hse = $this->buildUser($entreprise);
        $security = $this->securityFor($hse, ['ROLE_HSE']);

        $payload = $this->invokeController($intervention, $security);

        $this->assertArrayHasKey('intervention', $payload);
    }

    public function testPrestataireSideUserFromTheSameEntrepriseAsTheCreatorCanSeeIt(): void
    {
        $entreprise = $this->buildEntreprise();
        $creator = $this->buildUser($entreprise);
        $intervention = $this->buildIntervention(StatutIntervention::EN_COURS, $creator);

        $teammate = $this->buildUser($entreprise);
        $security = $this->securityFor($teammate, []);

        $payload = $this->invokeController($intervention, $security);

        $this->assertArrayHasKey('intervention', $payload);
    }

    public function testPrestataireSideUserFromADifferentEntrepriseIsDeniedWithA404(): void
    {
        $creator = $this->buildUser($this->buildEntreprise());
        $intervention = $this->buildIntervention(StatutIntervention::EN_COURS, $creator);

        $stranger = $this->buildUser($this->buildEntreprise());
        $security = $this->securityFor($stranger, []);

        $repository = $this->createMock(InterventionRepository::class);
        $repository->method('findForHseDetail')->willReturn($intervention);
        $controller = new InterventionDetailHseController($repository, $security);
        $controller->setContainer(new Container());

        $response = $controller->__invoke((string) $intervention->getId());

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testToaChefProjetAssignedToThePlanCanSeeIt(): void
    {
        $chefProjet = $this->buildUser(null);
        $intervention = $this->buildIntervention(StatutIntervention::EN_COURS, null, $chefProjet);

        $security = $this->securityFor($chefProjet, []);

        $payload = $this->invokeController($intervention, $security);

        $this->assertArrayHasKey('intervention', $payload);
    }

    public function testToaChefProjetNotAssignedToThePlanIsDeniedWithA404(): void
    {
        $assignedChefProjet = $this->buildUser(null);
        $intervention = $this->buildIntervention(StatutIntervention::EN_COURS, null, $assignedChefProjet);

        $anotherChefProjet = $this->buildUser(null);
        $security = $this->securityFor($anotherChefProjet, []);

        $repository = $this->createMock(InterventionRepository::class);
        $repository->method('findForHseDetail')->willReturn($intervention);
        $controller = new InterventionDetailHseController($repository, $security);
        $controller->setContainer(new Container());

        $response = $controller->__invoke((string) $intervention->getId());

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    /**
     * @return array<string, mixed>
     */
    private function invokeController(Intervention $intervention, Security $security): array
    {
        $repository = $this->createMock(InterventionRepository::class);
        $repository->method('findForHseDetail')->willReturn($intervention);

        $controller = new InterventionDetailHseController($repository, $security);
        // Empty container: AbstractController::json() falls back to a plain
        // json_encode() when no 'serializer' service is registered.
        $controller->setContainer(new Container());

        $response = $controller->__invoke((string) $intervention->getId());

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        return $payload;
    }

    private function bypassSecurity(): Security
    {
        return $this->securityFor($this->buildUser(null), ['ROLE_SUPER_ADMIN']);
    }

    private function securityFor(User $user, array $grantedRoles): Security
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);
        $security->method('isGranted')->willReturnCallback(
            static fn (string $role): bool => in_array($role, $grantedRoles, true),
        );

        return $security;
    }

    private function buildIntervention(StatutIntervention $statut, ?User $permitCreator = null, ?User $chefProjet = null): Intervention
    {
        $intervention = new Intervention();
        $intervention->setStatut($statut);

        $idProperty = new \ReflectionProperty(Intervention::class, 'id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($intervention, Uuid::v7());

        $createdAtProperty = new \ReflectionProperty(Intervention::class, 'createdAt');
        $createdAtProperty->setAccessible(true);
        $createdAtProperty->setValue($intervention, new \DateTimeImmutable('2026-01-01'));

        if ($permitCreator !== null || $chefProjet !== null) {
            $permit = new PermitTravail();
            $permit->setCodeSite('SITE-1');
            $permit->setType(TypePermitTravail::GENERAL);
            $permit->setProcessus(ProcessusPermitTravail::MAINTENANCE);
            if ($permitCreator !== null) {
                $permit->setCreatedBy($permitCreator);
            }

            if ($chefProjet !== null) {
                $plan = new PlanPrevention();
                $plan->setChefProjet($chefProjet);
                $permit->setPlanPrevention($plan);
            }

            $intervention->setPermitTravail($permit);
        }

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

    private function buildEntreprise(): Entreprise
    {
        $entreprise = new Entreprise();
        $entreprise->setNom('Entreprise ' . uniqid());

        $reflection = new \ReflectionProperty(Entreprise::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($entreprise, Uuid::v7());

        return $entreprise;
    }

    private static int $nextUserId = 1;

    private function buildUser(?Entreprise $entreprise): User
    {
        $user = new User();
        $user->setEmail('user-' . uniqid() . '@example.test');
        $user->setRoles(['ROLE_PRESTATAIRE']);
        $user->setEntreprise($entreprise);

        $reflection = new \ReflectionProperty(User::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($user, self::$nextUserId++);

        return $user;
    }
}
