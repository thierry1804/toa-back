<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\PermitTravail\Service;

use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Entity\PermitTravailDocument;
use App\Domain\PermitTravail\Enum\TypeDocumentPermitTravail;
use App\Domain\PermitTravail\Service\PermitTravailConsultationGuard;
use App\Domain\User\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class PermitTravailConsultationGuardTest extends TestCase
{
    public function testValidationBloqueeTantQueUnePieceNestPasOuverte(): void
    {
        $guard = new PermitTravailConsultationGuard();
        $permit = new PermitTravail();
        $permit->addDocument(
            $this->document(TypeDocumentPermitTravail::TROUSSE_SECOURS)
                ->markConsulted(new User(), new \DateTimeImmutable()),
        );
        $permit->addDocument($this->document(TypeDocumentPermitTravail::PV_SENSIBILISATION_HSE));
        $permit->addDocument($this->document(TypeDocumentPermitTravail::PHOTO_EPI_BASE, nonApplicable: true));

        try {
            $guard->assertAllConsulted($permit);
            $this->fail('documents_non_consultes attendu');
        } catch (UnprocessableEntityHttpException $e) {
            $this->assertStringContainsString('documents_non_consultes', $e->getMessage());
            $this->assertStringContainsString(TypeDocumentPermitTravail::PV_SENSIBILISATION_HSE->value, $e->getMessage());
        }
    }

    public function testResetConsultationsEffaceLaConsultation(): void
    {
        $guard = new PermitTravailConsultationGuard();
        $permit = new PermitTravail();
        $permit->addDocument(
            $this->document(TypeDocumentPermitTravail::TROUSSE_SECOURS)
                ->markConsulted(new User(), new \DateTimeImmutable()),
        );

        $guard->resetConsultations($permit);

        $this->assertNull($permit->getDocuments()->first()->getConsultedAt());
        $this->assertFalse($permit->isTousDocumentsConsultes());
    }

    private function document(TypeDocumentPermitTravail $type, bool $nonApplicable = false): PermitTravailDocument
    {
        return (new PermitTravailDocument())
            ->setType($type)
            ->setFilePath('permits-travail/x/' . $type->value . '.pdf')
            ->setMimeType('application/pdf')
            ->setUploadedAt(new \DateTimeImmutable())
            ->setNonApplicable($nonApplicable);
    }
}
