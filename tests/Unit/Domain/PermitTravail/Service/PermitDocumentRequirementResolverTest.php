<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\PermitTravail\Service;

use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Entity\PermitTravailDocument;
use App\Domain\PermitTravail\Enum\ProcessusPermitTravail;
use App\Domain\PermitTravail\Enum\TypeDocumentPermitTravail;
use App\Domain\PermitTravail\Enum\TypePermitTravail;
use App\Domain\PermitTravail\Service\PermitDocumentRequirementResolver;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\Referentiel\Entity\Site;
use App\Domain\Referentiel\Repository\SiteRepository;
use PHPUnit\Framework\TestCase;

class PermitDocumentRequirementResolverTest extends TestCase
{
    public function testClotureRequiresBeforeAndAfterPhotosForEveryProcess(): void
    {
        foreach (['Nouveau site', 'Maintenance préventive'] as $process) {
            $required = $this->resolver(null)->getRequiredClotureDocumentTypes(
                $this->permit(TypePermitTravail::GENERAL, ProcessusPermitTravail::NOUVEAU_SITE, $process),
            );

            $this->assertContains(TypeDocumentPermitTravail::PHOTO_AVANT_TRAVAUX, $required, $process);
            $this->assertContains(TypeDocumentPermitTravail::PHOTO_APRES_TRAVAUX, $required, $process);
            $this->assertContains(TypeDocumentPermitTravail::PV_FIN_TRAVAUX, $required, $process);
        }
    }

    public function testMissingClotureDocumentsListBothPhotos(): void
    {
        $permit = $this->permit(TypePermitTravail::GENERAL, ProcessusPermitTravail::MAINTENANCE, 'Maintenance préventive');
        $permit->addDocument($this->document(TypeDocumentPermitTravail::PV_FIN_TRAVAUX));
        $permit->addDocument($this->document(TypeDocumentPermitTravail::PHOTO_PROPRETE_SITE));

        $missing = $this->resolver(null)->findMissingClotureDocumentTypes($permit);

        $this->assertEqualsCanonicalizing(
            [TypeDocumentPermitTravail::PHOTO_AVANT_TRAVAUX, TypeDocumentPermitTravail::PHOTO_APRES_TRAVAUX],
            $missing,
        );
    }

    public function testEnvironmentalDocumentsAreRequiredOnlyForApnApiSites(): void
    {
        $permit = $this->permit(TypePermitTravail::GENERAL, ProcessusPermitTravail::NOUVEAU_SITE, 'Nouveau site');

        $standard = $this->resolver((new Site())->setCodeSite('ANK-001'))->getRequiredDocumentTypes($permit);
        $protected = $this->resolver((new Site())->setCodeSite('ANK-001')->setApn(true))->getRequiredDocumentTypes($permit);

        $this->assertNotContains(TypeDocumentPermitTravail::ENV_TRI_DECHETS, $standard);
        $this->assertContains(TypeDocumentPermitTravail::ENV_TRI_DECHETS, $protected);
        $this->assertContains(TypeDocumentPermitTravail::ENV_INVENTAIRE_ESPECES, $protected);
    }

    public function testEnvironmentalDocumentsFollowTheProcess(): void
    {
        $permit = $this->permit(TypePermitTravail::GENERAL, ProcessusPermitTravail::MAINTENANCE, 'Maintenance préventive');
        $site = (new Site())->setCodeSite('ANK-001')->setApi(true);

        $soumission = $this->resolver($site)->getRequiredDocumentTypes($permit);
        $cloture = $this->resolver($site)->getRequiredClotureDocumentTypes($permit);

        $this->assertContains(TypeDocumentPermitTravail::ENV_MATERIELS_DEVERSEMENT, $soumission);
        $this->assertNotContains(TypeDocumentPermitTravail::ENV_TRI_DECHETS, $soumission);
        $this->assertContains(TypeDocumentPermitTravail::ENV_QUANTITE_DECHETS, $cloture);
        $this->assertNotContains(TypeDocumentPermitTravail::ENV_REGISTRE_DECHETS, $cloture);
    }

    public function testEnvironmentalDocumentsAreNotRequiredOnSpecialisedPermits(): void
    {
        $permit = $this->permit(TypePermitTravail::HAUTEUR, ProcessusPermitTravail::NOUVEAU_SITE, 'Nouveau site');
        $site = (new Site())->setCodeSite('ANK-001')->setApn(true);

        $this->assertNotContains(TypeDocumentPermitTravail::ENV_TRI_DECHETS, $this->resolver($site)->getRequiredDocumentTypes($permit));
    }

    public function testNonApplicableMarkerSatisfiesEnvironmentalRequirement(): void
    {
        $permit = $this->permit(TypePermitTravail::GENERAL, ProcessusPermitTravail::MAINTENANCE, 'Maintenance préventive');
        $site = (new Site())->setCodeSite('ANK-001')->setApn(true);
        $permit->addDocument($this->document(TypeDocumentPermitTravail::ENV_PROPRETE_AVANT, nonApplicable: true));

        $missing = $this->resolver($site)->findMissingDocumentTypes($permit);

        $this->assertNotContains(TypeDocumentPermitTravail::ENV_PROPRETE_AVANT, $missing);
        $this->assertContains(TypeDocumentPermitTravail::ENV_MATERIELS_DEVERSEMENT, $missing);
    }

    private function resolver(?Site $site): PermitDocumentRequirementResolver
    {
        $repository = $this->createStub(SiteRepository::class);
        $repository->method('findByCodeSite')->willReturn($site);

        return new PermitDocumentRequirementResolver($repository);
    }

    private function permit(TypePermitTravail $type, ProcessusPermitTravail $processus, string $planProcess): PermitTravail
    {
        $plan = (new PlanPrevention())->setTypeIntervention($planProcess);

        return (new PermitTravail())
            ->setType($type)
            ->setProcessus($processus)
            ->setCodeSite('ANK-001')
            ->setPlanPrevention($plan);
    }

    private function document(TypeDocumentPermitTravail $type, bool $nonApplicable = false): PermitTravailDocument
    {
        return (new PermitTravailDocument())
            ->setType($type)
            ->setFilePath($nonApplicable ? '' : 'permits-travail/x/' . $type->value . '.pdf')
            ->setMimeType($nonApplicable ? '' : 'application/pdf')
            ->setUploadedAt(new \DateTimeImmutable())
            ->setNonApplicable($nonApplicable);
    }
}
