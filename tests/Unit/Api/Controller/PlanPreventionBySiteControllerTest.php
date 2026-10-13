<?php

declare(strict_types=1);

namespace App\Tests\Unit\Api\Controller;

use App\Api\Controller\PlanPreventionBySiteController;
use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Entity\PermitTravailGroupe;
use App\Domain\PermitTravail\Enum\StatutPermitTravail;
use App\Domain\PermitTravail\Enum\TypePermitTravail;
use App\Domain\PermitTravail\Repository\PermitTravailGroupeRepository;
use App\Domain\PermitTravail\Repository\PermitTravailRepository;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Enum\StatutPlanPrevention;
use App\Domain\PlanPrevention\Repository\PlanPreventionRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

class PlanPreventionBySiteControllerTest extends TestCase
{
    private const CODE_SITE = 'ANK-001';

    public function testAutreProcessusAvecGeneralSoumisResteSelectionnable(): void
    {
        $plan = $this->plan('VDR');
        $general = $this->permit(TypePermitTravail::GENERAL, StatutPermitTravail::SOUMIS, $plan);

        $response = $this->invoke($plan, permitRepoFindBy: [$general]);

        $plans = $response['plans'];
        self::assertCount(1, $plans);
        self::assertSame($general->getId()?->toRfc4122(), $plans[0]['permitGeneralId']);
    }

    public function testAutreProcessusAvecGeneralEtHauteurEnCoursResteSelectionnable(): void
    {
        // Un Général SOUMIS et un Hauteur VALIDE_HSE existent déjà sur le site :
        // le plan ne doit toujours pas être exclu (bug 1).
        $plan = $this->plan('Infra');
        $general = $this->permit(TypePermitTravail::GENERAL, StatutPermitTravail::SOUMIS, $plan);

        $response = $this->invoke($plan, permitRepoFindBy: [$general]);

        $plans = $response['plans'];
        self::assertCount(1, $plans, 'le plan doit rester sélectionnable malgré un permis Hauteur en cours');
        self::assertSame($general->getId()?->toRfc4122(), $plans[0]['permitGeneralId']);
    }

    public function testAutreProcessusAvecPlusieursGenerauxRetientLePlusRecent(): void
    {
        $plan = $this->plan('Energie');
        $ancienGeneral = $this->permit(TypePermitTravail::GENERAL, StatutPermitTravail::CLOTURE, $plan, new \DateTimeImmutable('-10 days'));
        $nouveauGeneral = $this->permit(TypePermitTravail::GENERAL, StatutPermitTravail::SOUMIS, $plan, new \DateTimeImmutable('-1 day'));

        // Le contrôleur doit interroger le repository avec orderBy createdAt DESC
        // et limit 1 : on simule ici la réponse que Doctrine renverrait dans ce cas,
        // c'est-à-dire uniquement le plus récent des deux Généraux.
        $response = $this->invoke(
            $plan,
            permitRepoFindBy: [$nouveauGeneral],
            expectedPermitCriteria: ['codeSite' => self::CODE_SITE, 'planPrevention' => $plan, 'type' => TypePermitTravail::GENERAL],
            expectedPermitOrderBy: ['createdAt' => 'DESC'],
            expectedPermitLimit: 1,
        );

        $plans = $response['plans'];
        self::assertCount(1, $plans);
        self::assertSame($nouveauGeneral->getId()?->toRfc4122(), $plans[0]['permitGeneralId']);
        self::assertNotSame($ancienGeneral->getId()?->toRfc4122(), $plans[0]['permitGeneralId']);
    }

    public function testAutreProcessusSansAucunPermisResteSelectionnableSansGeneral(): void
    {
        $plan = $this->plan('Maintenance');

        $response = $this->invoke($plan, permitRepoFindBy: []);

        $plans = $response['plans'];
        self::assertCount(1, $plans);
        self::assertNull($plans[0]['permitGeneralId']);
    }

    public function testNouveauSiteAvecGeneralSeulResteSelectionnableNonRegression(): void
    {
        $plan = $this->plan('Nouveau site');
        $general = $this->permit(TypePermitTravail::GENERAL, StatutPermitTravail::SOUMIS, $plan);

        $groupe = (new PermitTravailGroupe())
            ->setCodeSite(self::CODE_SITE)
            ->setPlanPrevention($plan)
            ->setPermitGeneral($general);

        $response = $this->invoke($plan, groupe: $groupe);

        $plans = $response['plans'];
        self::assertCount(1, $plans, 'Nouveau site : le plan doit rester sélectionnable tant que le spécialisé n\'existe pas');
        self::assertSame($general->getId()?->toRfc4122(), $plans[0]['permitGeneralId']);
    }

    public function testNouveauSiteAvecPaireCompleteExclutLePlanNonRegression(): void
    {
        $plan = $this->plan('Nouveau site');
        $general = $this->permit(TypePermitTravail::GENERAL, StatutPermitTravail::SOUMIS, $plan);
        $specialise = $this->permit(TypePermitTravail::HAUTEUR, StatutPermitTravail::SOUMIS, $plan);

        $groupe = (new PermitTravailGroupe())
            ->setCodeSite(self::CODE_SITE)
            ->setPlanPrevention($plan)
            ->setPermitGeneral($general)
            ->setPermitSpecialise($specialise);

        $response = $this->invoke($plan, groupe: $groupe);

        self::assertCount(0, $response['plans'], 'Nouveau site : la paire complète doit exclure le plan des résultats');
    }

    private function plan(string $typeIntervention): PlanPrevention
    {
        $plan = (new PlanPrevention())
            ->setReference('PP-' . $typeIntervention)
            ->setCodeSite(self::CODE_SITE)
            ->setLocalite('Ankazobe')
            ->setActivitePlanifiee('Travaux')
            ->setDateDebut(new \DateTimeImmutable('today'))
            ->setDateFin(new \DateTimeImmutable('today'))
            ->setStatut(StatutPlanPrevention::VALIDE_HSE)
            ->setTypeIntervention($typeIntervention);

        $this->setId($plan, Uuid::v4());

        return $plan;
    }

    private function permit(
        TypePermitTravail $type,
        StatutPermitTravail $statut,
        PlanPrevention $plan,
        ?\DateTimeImmutable $createdAt = null,
    ): PermitTravail {
        $permit = (new PermitTravail())
            ->setReference('PT-' . $type->value . '-' . bin2hex(random_bytes(4)))
            ->setCodeSite(self::CODE_SITE)
            ->setPlanPrevention($plan)
            ->setType($type)
            ->setStatut($statut);

        $this->setId($permit, Uuid::v4());

        if ($createdAt !== null) {
            $ref = new \ReflectionProperty(PermitTravail::class, 'createdAt');
            $ref->setAccessible(true);
            $ref->setValue($permit, $createdAt);
        }

        return $permit;
    }

    private function setId(object $entity, Uuid $uuid): void
    {
        $ref = new \ReflectionProperty($entity, 'id');
        $ref->setAccessible(true);
        $ref->setValue($entity, $uuid);
    }

    /**
     * @param list<PermitTravail> $permitRepoFindBy
     */
    private function invoke(
        PlanPrevention $plan,
        array $permitRepoFindBy = [],
        ?PermitTravailGroupe $groupe = null,
        ?array $expectedPermitCriteria = null,
        ?array $expectedPermitOrderBy = null,
        ?int $expectedPermitLimit = null,
    ): array {
        $planRepository = $this->createMock(PlanPreventionRepository::class);
        $planRepository->method('findBy')
            ->with(['statut' => StatutPlanPrevention::VALIDE_HSE])
            ->willReturn([$plan]);

        // Stub (pas de vérification d'interaction) pour le repository PermitTravail,
        // sauf quand le test veut explicitement vérifier les critères/tri/limite
        // envoyés par le contrôleur (cas "plusieurs Généraux successifs").
        $permitRepository = $this->createStub(PermitTravailRepository::class);
        if ($expectedPermitCriteria !== null) {
            $permitRepository->method('findBy')->willReturnCallback(
                function (array $criteria, ?array $orderBy = null, ?int $limit = null) use (
                    $expectedPermitCriteria,
                    $expectedPermitOrderBy,
                    $expectedPermitLimit,
                    $permitRepoFindBy,
                ): array {
                    self::assertSame($expectedPermitCriteria, $criteria);
                    self::assertSame($expectedPermitOrderBy, $orderBy);
                    self::assertSame($expectedPermitLimit, $limit);

                    return $permitRepoFindBy;
                },
            );
        } else {
            $permitRepository->method('findBy')->willReturn($permitRepoFindBy);
        }

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnMap([
            [PlanPrevention::class, $planRepository],
            [PermitTravail::class, $permitRepository],
        ]);

        $groupeRepository = $this->createStub(PermitTravailGroupeRepository::class);
        $groupeRepository->method('findByCodeSiteAndPlan')->willReturn($groupe);

        $controller = new PlanPreventionBySiteController($entityManager, $groupeRepository);
        $controller->setContainer(new Container());

        $response = $controller(new Request(query: ['codeSite' => self::CODE_SITE]));

        /** @var array{plans: list<array<string, mixed>>} $data */
        $data = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $data;
    }
}
