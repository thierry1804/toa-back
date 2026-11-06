<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\PlanPrevention\Service;

use App\Domain\PlanPrevention\Entity\DocumentPrevention;
use App\Domain\PlanPrevention\Entity\ModeOperatoirePlanPrevention;
use App\Domain\PlanPrevention\Entity\PhasePlanPrevention;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Entity\RisquePrevention;
use App\Domain\PlanPrevention\Enum\TypeDocumentPrevention;
use App\Domain\PlanPrevention\Service\PlanPreventionSubmissionValidator;
use App\Domain\Referentiel\Entity\CategorieRisque;
use App\Domain\User\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class PlanPreventionSubmissionValidatorTest extends TestCase
{
    private PlanPreventionSubmissionValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PlanPreventionSubmissionValidator();
    }

    public function testPlanWithoutApnApiSiteNeedsNoApnApiRisk(): void
    {
        $plan = $this->planWithAllDocuments();

        $this->validator->assertSubmittable($plan);

        $this->addToAssertionCount(1);
    }

    public function testApnApiSiteWithoutApnApiRiskIsRefused(): void
    {
        $plan = $this->planWithAllDocuments();
        $plan->setSitesApnApi([['codeSite' => 'ANK-001', 'nomSite' => 'Ankazobe', 'apn' => true, 'api' => false]]);
        $plan->addRisque($this->risqueWithCategory((new CategorieRisque())->setNom('Incendie')));

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('risque_apn_api_obligatoire');

        $this->validator->assertSubmittable($plan);
    }

    public function testApnApiSiteWithApnRiskIsAccepted(): void
    {
        $plan = $this->planWithAllDocuments();
        $plan->setSitesApnApi([['codeSite' => 'ANK-001', 'nomSite' => 'Ankazobe', 'apn' => true, 'api' => false]]);
        $plan->addRisque($this->risqueWithCategory((new CategorieRisque())->setNom('Faune')->setTypeSite('APN')));

        $this->validator->assertSubmittable($plan);

        $this->addToAssertionCount(1);
    }

    public function testRiskCategoryInheritsTypeSiteFromParent(): void
    {
        $plan = $this->planWithAllDocuments();
        $plan->setSitesApnApi([['codeSite' => 'ANK-001', 'nomSite' => 'Ankazobe', 'apn' => false, 'api' => true]]);
        $root = (new CategorieRisque())->setNom('Aire protégée')->setTypeSite('API');
        $child = (new CategorieRisque())->setNom('Faune')->setParent($root);
        $plan->addRisque($this->risqueWithCategory($child));

        $this->assertTrue($this->validator->hasApnApiRisk($plan));
    }

    public function testApiSiteIsNotSatisfiedByApnRisk(): void
    {
        $plan = $this->planWithAllDocuments();
        $plan->setSitesApnApi([['codeSite' => 'ANK-001', 'nomSite' => 'Ankazobe', 'apn' => false, 'api' => true]]);
        $plan->addRisque($this->risqueWithCategory((new CategorieRisque())->setNom('Atteinte à la faune et à la flore (APN)')->setTypeSite('APN')));

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('risque_apn_api_obligatoire: API');

        $this->validator->assertSubmittable($plan);
    }

    public function testGenericAutreRisqueDoesNotSatisfyObligation(): void
    {
        $plan = $this->planWithAllDocuments();
        $plan->setSitesApnApi([['codeSite' => 'ANK-001', 'nomSite' => 'Ankazobe', 'apn' => true, 'api' => false]]);
        $plan->addRisque($this->risqueWithCategory((new CategorieRisque())->setNom('Autre(s) risque(s) APN à préciser')->setTypeSite('APN')));

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('risque_apn_api_obligatoire: APN');

        $this->validator->assertSubmittable($plan);
    }

    public function testMixedApnAndApiSitesRequireBothRiskTypes(): void
    {
        $plan = $this->planWithAllDocuments();
        $plan->setSitesApnApi([
            ['codeSite' => 'ANK-001', 'nomSite' => 'Ankazobe', 'apn' => true, 'api' => false],
            ['codeSite' => 'ANK-002', 'nomSite' => 'Andasibe', 'apn' => false, 'api' => true],
        ]);
        $plan->addRisque($this->risqueWithCategory((new CategorieRisque())->setNom('Faune APN')->setTypeSite('APN')));

        try {
            $this->validator->assertSubmittable($plan);
            $this->fail('Une exception est attendue tant que le risque API manque.');
        } catch (UnprocessableEntityHttpException $e) {
            $this->assertStringContainsString('risque_apn_api_obligatoire: API', $e->getMessage());
        }

        $plan->addRisque($this->risqueWithCategory((new CategorieRisque())->setNom('Faune API')->setTypeSite('API')));
        $this->validator->assertSubmittable($plan);
        $this->addToAssertionCount(1);
    }

    public function testNonApplicableMarkerSatisfiesRequiredType(): void
    {
        $plan = new PlanPrevention();
        foreach (PlanPreventionSubmissionValidator::REQUIRED_DOCUMENT_TYPES as $type) {
            $plan->addDocument($this->document($type, nonApplicable: $type === TypeDocumentPrevention::LISTE_VEHICULES));
        }

        $this->assertSame(
            [],
            $this->validator->findMissingDocumentTypes($plan, PlanPreventionSubmissionValidator::REQUIRED_DOCUMENT_TYPES),
        );
    }

    public function testMissingTypeIsReported(): void
    {
        $plan = new PlanPrevention();
        $plan->addDocument($this->document(TypeDocumentPrevention::FDS));

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('documents_manquants');

        $this->validator->assertSubmittable($plan);
    }

    // ===== R-09 : une photo par ligne équipements pour « Nouveau site » =====
    // (la liste installations a été déplacée vers le permis de travail Général)

    public function testNouveauSiteRowsWithoutPhotoAreRefusedWithLineNumbers(): void
    {
        $plan = $this->planWithAllDocuments();
        $plan->setTypeIntervention(PlanPreventionSubmissionValidator::TYPE_INTERVENTION_NOUVEAU_SITE);
        $plan->setEquipements([
            ['description' => 'Harnais', 'conditionsUtilisation' => '', 'photoDocumentId' => 'inconnu'],
            ['description' => 'Casque', 'conditionsUtilisation' => ''],
        ]);

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('photos_lignes_manquantes: equipements:1,2');

        $this->validator->assertSubmittable($plan);
    }

    public function testNouveauSiteInstallationsAreNoLongerChecked(): void
    {
        $plan = $this->planWithAllDocuments();
        $plan->setTypeIntervention(PlanPreventionSubmissionValidator::TYPE_INTERVENTION_NOUVEAU_SITE);
        $plan->setEquipements([['description' => 'Harnais', 'conditionsUtilisation' => '']]);
        $equipement = $this->document(TypeDocumentPrevention::PHOTOS_EQUIPEMENTS, withId: true);
        $plan->addDocument($equipement);
        $plan->setEquipements([['description' => 'Harnais', 'conditionsUtilisation' => '', 'photoDocumentId' => (string) $equipement->getId()]]);
        // Installations sans aucune photo : ne doit plus bloquer la soumission.
        $plan->setInstallations([['description' => 'Groupe électrogène', 'conditionsUtilisation' => '']]);

        $this->validator->assertSubmittable($plan);

        $this->addToAssertionCount(1);
    }

    public function testNouveauSitePhotoOfWrongTypeDoesNotCount(): void
    {
        $plan = $this->planWithAllDocuments();
        $plan->setTypeIntervention(PlanPreventionSubmissionValidator::TYPE_INTERVENTION_NOUVEAU_SITE);
        $photo = $this->document(TypeDocumentPrevention::PHOTOS_INSTALLATIONS, withId: true);
        $plan->addDocument($photo);
        $plan->setEquipements([['description' => 'Harnais', 'conditionsUtilisation' => '', 'photoDocumentId' => (string) $photo->getId()]]);

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('photos_lignes_manquantes: equipements:1');

        $this->validator->assertSubmittable($plan);
    }

    public function testNouveauSiteWithAPhotoOnEveryRowIsAccepted(): void
    {
        $plan = $this->planWithAllDocuments();
        $plan->setTypeIntervention(PlanPreventionSubmissionValidator::TYPE_INTERVENTION_NOUVEAU_SITE);
        $installation = $this->document(TypeDocumentPrevention::PHOTOS_INSTALLATIONS, withId: true);
        $equipement = $this->document(TypeDocumentPrevention::PHOTOS_EQUIPEMENTS, withId: true);
        $plan->addDocument($installation);
        $plan->addDocument($equipement);
        $plan->setInstallations([['description' => 'Groupe', 'conditionsUtilisation' => '', 'photoDocumentId' => (string) $installation->getId()]]);
        $plan->setEquipements([['description' => 'Harnais', 'conditionsUtilisation' => '', 'photoDocumentId' => (string) $equipement->getId()]]);

        $this->validator->assertSubmittable($plan);

        $this->addToAssertionCount(1);
    }

    public function testNouveauSiteNoLongerRequiresPhotoDocumentsInTheDocumentsStep(): void
    {
        $plan = $this->planWithAllDocuments();
        $plan->setTypeIntervention(PlanPreventionSubmissionValidator::TYPE_INTERVENTION_NOUVEAU_SITE);

        $this->validator->assertSubmittable($plan);

        $this->addToAssertionCount(1);
    }

    public function testNonNouveauSiteRowsNeedNoPhoto(): void
    {
        $plan = $this->planWithAllDocuments();
        $plan->setTypeIntervention('Vie de Réseau');
        $plan->setEquipements([['description' => 'Harnais', 'conditionsUtilisation' => '']]);

        $this->validator->assertSubmittable($plan);

        $this->addToAssertionCount(1);
    }

    public function testNonNouveauSitePlanDoesNotRequirePhotos(): void
    {
        $plan = $this->planWithAllDocuments();
        $plan->setTypeIntervention('Vie de Réseau');

        $this->validator->assertSubmittable($plan);

        $this->addToAssertionCount(1);
    }

    public function testNonNouveauSitePlanAcceptsPhotosMarkedNonApplicable(): void
    {
        $plan = $this->planWithAllDocuments();
        $plan->setTypeIntervention('Infrastructure');
        $plan->addDocument($this->document(TypeDocumentPrevention::PHOTOS_INSTALLATIONS, nonApplicable: true));
        $plan->addDocument($this->document(TypeDocumentPrevention::PHOTOS_EQUIPEMENTS, nonApplicable: true));

        $this->validator->assertSubmittable($plan);

        $this->addToAssertionCount(1);
    }

    // ===== MODOP obligatoire pour les plans créés par un prestataire =====

    public function testPrestataireCreatedPlanWithoutModeOperatoireIsRefused(): void
    {
        $plan = $this->planWithAllDocuments();
        $plan->setCreatedBy($this->prestataire());

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('mode_operatoire_requis');

        $this->validator->assertSubmittable($plan);
    }

    public function testPrestataireCreatedPlanWithEmptySectionIsRefused(): void
    {
        $plan = $this->planWithAllDocuments();
        $plan->setCreatedBy($this->prestataire());
        $plan->addSection((new PhasePlanPrevention())->setLibelle('Phase 1'));

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('mode_operatoire_requis');

        $this->validator->assertSubmittable($plan);
    }

    public function testPrestataireCreatedPlanWithAModeOperatoireIsAccepted(): void
    {
        $plan = $this->planWithAllDocuments();
        $plan->setCreatedBy($this->prestataire());
        $phase = (new PhasePlanPrevention())->setLibelle('Phase 1');
        $phase->addModeOperatoire((new ModeOperatoirePlanPrevention())->setTache('Montage'));
        $plan->addSection($phase);

        $this->validator->assertSubmittable($plan);

        $this->addToAssertionCount(1);
    }

    public function testAdminCreatedPlanWithoutModeOperatoireIsAccepted(): void
    {
        $plan = $this->planWithAllDocuments();
        $plan->setCreatedBy((new User())->setRoles(['ROLE_ADMIN']));

        $this->validator->assertSubmittable($plan);

        $this->addToAssertionCount(1);
    }

    public function testPlanWithoutCreatedByIsAccepted(): void
    {
        $plan = $this->planWithAllDocuments();

        $this->validator->assertSubmittable($plan);

        $this->addToAssertionCount(1);
    }

    private function prestataire(): User
    {
        return (new User())->setRoles(['ROLE_PRESTATAIRE']);
    }

    private function planWithAllDocuments(): PlanPrevention
    {
        $plan = new PlanPrevention();
        foreach (PlanPreventionSubmissionValidator::REQUIRED_DOCUMENT_TYPES as $type) {
            $plan->addDocument($this->document($type));
        }

        return $plan;
    }

    private function document(TypeDocumentPrevention $type, bool $nonApplicable = false, bool $withId = false): DocumentPrevention
    {
        $document = (new DocumentPrevention())
            ->setType($type)
            ->setFilePath($nonApplicable ? '' : 'plans-prevention/x/' . $type->value . '.pdf')
            ->setMimeType($nonApplicable ? '' : 'application/pdf')
            ->setUploadedAt(new \DateTimeImmutable())
            ->setNonApplicable($nonApplicable);

        if ($withId) {
            (new \ReflectionProperty(DocumentPrevention::class, 'id'))->setValue($document, \Symfony\Component\Uid\Uuid::v7());
        }

        return $document;
    }

    private function risqueWithCategory(CategorieRisque $categorie): RisquePrevention
    {
        return (new RisquePrevention())->setCategoriesRisque([$categorie]);
    }
}
