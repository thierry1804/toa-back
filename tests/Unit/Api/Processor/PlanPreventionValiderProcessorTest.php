<?php

declare(strict_types=1);

namespace App\Tests\Unit\Api\Processor;

use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Processor\PlanPreventionValiderProcessor;
use App\Domain\PlanPrevention\Entity\DocumentPrevention;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Enum\StatutPlanPrevention;
use App\Domain\PlanPrevention\Enum\TypeDocumentPrevention;
use App\Domain\PlanPrevention\Service\PlanPreventionConsultationGuard;
use App\Domain\PlanPrevention\Service\PlanPreventionNotificationService;
use App\Domain\PlanPrevention\Service\PlanPreventionSubmissionValidator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class PlanPreventionValiderProcessorTest extends TestCase
{
    public function testValiderRefuseTantQueUnePieceNaPasEteOuverte(): void
    {
        $plan = (new PlanPrevention())->setStatut(StatutPlanPrevention::EXAMINE);
        $plan->addDocument($this->document(TypeDocumentPrevention::FDS));

        $validator = $this->createMock(PlanPreventionSubmissionValidator::class);
        $validator->method('findMissingDocumentTypes')->willReturn([]);

        $processor = new PlanPreventionValiderProcessor(
            $this->createMock(ProcessorInterface::class),
            $this->createMock(EntityManagerInterface::class),
            new TokenStorage(),
            $validator,
            $this->createMock(PlanPreventionNotificationService::class),
            new PlanPreventionConsultationGuard(),
        );

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('documents_non_consultes');

        $processor->process($plan, new Post());
    }

    private function document(TypeDocumentPrevention $type): DocumentPrevention
    {
        return (new DocumentPrevention())
            ->setType($type)
            ->setFilePath('plans-prevention/x/' . $type->value . '.pdf')
            ->setMimeType('application/pdf')
            ->setUploadedAt(new \DateTimeImmutable());
    }
}
