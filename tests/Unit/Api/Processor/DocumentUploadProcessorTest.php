<?php

declare(strict_types=1);

namespace App\Tests\Unit\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Processor\DocumentUploadProcessor;
use App\Domain\PlanPrevention\Entity\DocumentPrevention;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Enum\TypeDocumentPrevention;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

class DocumentUploadProcessorTest extends TestCase
{
    private string $imagePath;

    protected function setUp(): void
    {
        $this->imagePath = tempnam(sys_get_temp_dir(), 'toa_photo_') ?: '';
        file_put_contents($this->imagePath, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
        ));
    }

    protected function tearDown(): void
    {
        @unlink($this->imagePath);
    }

    public function testSecondPhotoOfTheSameTypeDoesNotReplaceTheFirst(): void
    {
        $plan = new PlanPrevention();
        $existing = (new DocumentPrevention())
            ->setType(TypeDocumentPrevention::FDS)
            ->setFilePath('plans-prevention/x/FDS/first.png')
            ->setMimeType('image/png')
            ->setUploadedAt(new \DateTimeImmutable('-1 hour'));
        $plan->addDocument($existing);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->willReturn($plan);
        $entityManager->expects($this->never())->method('remove');

        $storage = $this->createMock(FilesystemOperator::class);
        $storage->expects($this->never())->method('delete');
        $storage->expects($this->once())->method('write');

        $persist = $this->createStub(ProcessorInterface::class);
        $persist->method('process')->willReturnArgument(0);

        $capturedAt = (new \DateTimeImmutable('-1 day', new \DateTimeZone('UTC')))->setTime(8, 0);
        $request = new Request(
            request: ['type' => 'FDS', 'capturedAt' => $capturedAt->format('c')],
            files: ['file' => new UploadedFile($this->imagePath, 'photo.png', 'image/png', null, true)],
        );
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $processor = new DocumentUploadProcessor($persist, $entityManager, $requestStack, $storage);
        $planId = Uuid::v4()->toRfc4122();

        $saved = $processor->process(null, $this->createStub(Operation::class), ['planPreventionId' => $planId]);

        $this->assertInstanceOf(DocumentPrevention::class, $saved);
        $this->assertSame(
            $capturedAt->format('Y-m-d H:i:s'),
            $saved->getCapturedAt()?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        );
        $this->assertCount(1, $plan->getDocuments());
        $this->assertSame($existing, $plan->getDocuments()->first());
    }
}
