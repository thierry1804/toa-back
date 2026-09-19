<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\PlanPrevention\Service;

use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Processor\PlanPreventionExaminerProcessor;
use App\Domain\PlanPrevention\Entity\DocumentPrevention;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Enum\StatutPlanPrevention;
use App\Domain\PlanPrevention\Enum\TypeDocumentPrevention;
use App\Domain\PlanPrevention\Service\PlanPreventionConsultationGuard;
use App\Domain\PlanPrevention\Service\PlanPreventionNotificationService;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class PlanPreventionConsultationGuardTest extends TestCase
{
    public function testAssertAllConsultedFailsUntilEveryApplicableDocumentIsConsulted(): void
    {
        $guard = new PlanPreventionConsultationGuard();
        $plan = new PlanPrevention();
        $consulted = $this->document(TypeDocumentPrevention::FDS)->markConsulted(new User(), new \DateTimeImmutable());
        $plan->addDocument($consulted);
        $plan->addDocument($this->document(TypeDocumentPrevention::PLAN_URGENCE));
        $plan->addDocument($this->document(TypeDocumentPrevention::LISTE_VEHICULES, nonApplicable: true));

        try {
            $guard->assertAllConsulted($plan);
            $this->fail('documents_non_consultes attendu');
        } catch (UnprocessableEntityHttpException $e) {
            $this->assertStringContainsString('documents_non_consultes', $e->getMessage());
            $this->assertStringContainsString(TypeDocumentPrevention::PLAN_URGENCE->value, $e->getMessage());
        }
    }

    public function testResetConsultationsClearsEveryDocument(): void
    {
        $guard = new PlanPreventionConsultationGuard();
        $plan = new PlanPrevention();
        $plan->addDocument($this->document(TypeDocumentPrevention::FDS)->markConsulted(new User(), new \DateTimeImmutable()));

        $guard->resetConsultations($plan);

        $document = $plan->getDocuments()->first();
        $this->assertNull($document->getConsultedAt());
        $this->assertNull($document->getConsultedBy());
    }

    public function testExaminerResetsConsultationsSoHseMustConsultAgain(): void
    {
        $guard = new PlanPreventionConsultationGuard();
        $plan = (new PlanPrevention())->setStatut(StatutPlanPrevention::SOUMIS);
        $plan->addDocument($this->document(TypeDocumentPrevention::FDS)->markConsulted(new User(), new \DateTimeImmutable()));

        $persist = $this->createMock(ProcessorInterface::class);
        $persist->method('process')->willReturnArgument(0);
        $storage = new TokenStorage();
        $storage->setToken(new UsernamePasswordToken(new User(), 'main', []));

        $processor = new PlanPreventionExaminerProcessor(
            $persist,
            $this->createMock(EntityManagerInterface::class),
            $storage,
            $this->createMock(PlanPreventionNotificationService::class),
            new RequestStack(),
            $guard,
        );

        $processor->process($plan, new Post());

        $this->assertSame(StatutPlanPrevention::EXAMINE, $plan->getStatut());
        $this->assertNull($plan->getDocuments()->first()->getConsultedAt());

        $this->expectException(UnprocessableEntityHttpException::class);
        $guard->assertAllConsulted($plan);
    }

    public function testApnApiSitesAreResolvedLazily(): void
    {
        $calls = new \ArrayObject(['n' => 0]);
        $plan = (new PlanPrevention())->setSitesApnApiLoader(static function () use ($calls): array {
            ++$calls['n'];

            return [['codeSite' => 'ANK-001', 'nomSite' => 'Ankazobe', 'apn' => true, 'api' => false]];
        });

        $this->assertSame(0, $calls['n']);
        $this->assertTrue($plan->getHasApnApiSite());
        $plan->getSitesApnApi();
        $this->assertSame(1, $calls['n']);
    }

    private function document(TypeDocumentPrevention $type, bool $nonApplicable = false): DocumentPrevention
    {
        return (new DocumentPrevention())
            ->setType($type)
            ->setFilePath('plans-prevention/x/' . $type->value . '.pdf')
            ->setMimeType('application/pdf')
            ->setUploadedAt(new \DateTimeImmutable())
            ->setNonApplicable($nonApplicable);
    }
}
