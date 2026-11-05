<?php

declare(strict_types=1);

namespace App\Tests\Unit\Api\Processor;

use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Processor\RisquePreventionProcessor;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\PlanPrevention\Entity\RisquePrevention;
use App\Domain\Referentiel\Entity\CategorieRisque;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * Un sous-risque (catégorie enfant) est obligatoire : la catégorie racine
 * seule reste trop vague pour pré-remplir les permis de travail.
 */
class RisquePreventionProcessorTest extends TestCase
{
    private ProcessorInterface&MockObject $persistProcessor;
    private EntityManagerInterface&MockObject $entityManager;
    private RisquePreventionProcessor $processor;
    private PlanPrevention $plan;

    protected function setUp(): void
    {
        $this->persistProcessor = $this->createMock(ProcessorInterface::class);
        $this->persistProcessor->method('process')->willReturnArgument(0);

        $this->plan = new PlanPrevention();
        $idProp = new \ReflectionProperty(PlanPrevention::class, 'id');
        $idProp->setAccessible(true);
        $idProp->setValue($this->plan, Uuid::v7());

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager->method('find')->willReturn($this->plan);

        $this->processor = new RisquePreventionProcessor($this->persistProcessor, $this->entityManager);
    }

    public function testRejectsRiskWithNoCategoryAtAll(): void
    {
        $risque = new RisquePrevention();

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('sous_risque_requis');

        $this->processor->process($risque, new Post(), ['planPreventionId' => (string) $this->plan->getId()]);
    }

    public function testRejectsRiskWithOnlyARootCategory(): void
    {
        $risque = (new RisquePrevention())->setCategoriesRisque([$this->categorie()]);

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('sous_risque_requis');

        $this->processor->process($risque, new Post(), ['planPreventionId' => (string) $this->plan->getId()]);
    }

    public function testAcceptsRiskWithAChildCategory(): void
    {
        $root = $this->categorie();
        $child = $this->categorie($root);

        $risque = (new RisquePrevention())->setCategoriesRisque([$root, $child]);

        $this->persistProcessor->expects($this->once())->method('process');

        $this->processor->process($risque, new Post(), ['planPreventionId' => (string) $this->plan->getId()]);
    }

    private function categorie(?CategorieRisque $parent = null): CategorieRisque
    {
        $categorie = (new CategorieRisque())->setNom('Catégorie ' . uniqid());
        if ($parent !== null) {
            $categorie->setParent($parent);
        }

        return $categorie;
    }
}
