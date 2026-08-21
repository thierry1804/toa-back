<?php

declare(strict_types=1);

namespace App\Domain\PlanPrevention\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Api\Processor\RisquePreventionProcessor;
use App\Domain\Referentiel\Entity\CategorieRisque;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: '`risque_prevention`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/plans-prevention/{planPreventionId}/risques',
            uriVariables: [
                'planPreventionId' => new Link(
                    fromClass: PlanPrevention::class,
                    toProperty: 'planPrevention',
                ),
            ],
            read: false,
            security: "is_granted('PLAN_PREVENTION_EDIT')",
            processor: RisquePreventionProcessor::class,
            name: 'risque_prevention_create',
        ),
        new Patch(
            uriTemplate: '/plans-prevention/{planPreventionId}/risques/{id}',
            uriVariables: [
                'planPreventionId' => new Link(
                    fromClass: PlanPrevention::class,
                    toProperty: 'planPrevention',
                ),
                'id' => new Link(fromClass: RisquePrevention::class),
            ],
            security: "is_granted('PLAN_PREVENTION_EDIT', object.getPlanPrevention())",
            name: 'risque_prevention_update',
        ),
        new Delete(
            uriTemplate: '/plans-prevention/{planPreventionId}/risques/{id}',
            uriVariables: [
                'planPreventionId' => new Link(
                    fromClass: PlanPrevention::class,
                    toProperty: 'planPrevention',
                ),
                'id' => new Link(fromClass: RisquePrevention::class),
            ],
            security: "is_granted('PLAN_PREVENTION_EDIT', object.getPlanPrevention())",
            name: 'risque_prevention_delete',
        ),
    ],
    normalizationContext: ['groups' => ['risque_prevention:read']],
    denormalizationContext: ['groups' => ['risque_prevention:write']],
)]
class RisquePrevention
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['risque_prevention:read', 'plan_prevention:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: PlanPrevention::class, inversedBy: 'risques')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PlanPrevention $planPrevention = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['risque_prevention:read', 'risque_prevention:write', 'plan_prevention:read'])]
    #[Assert\NotBlank(message: 'description_required')]
    #[Assert\NotNull(message: 'description_required')]
    private ?string $description = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['risque_prevention:read', 'risque_prevention:write', 'plan_prevention:read'])]
    #[Assert\NotNull(message: 'gravite_required')]
    #[Assert\Range(min: 1, max: 5, notInRangeMessage: 'gravite_range')]
    private ?int $gravite = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['risque_prevention:read', 'risque_prevention:write', 'plan_prevention:read'])]
    #[Assert\NotNull(message: 'probabilite_required')]
    #[Assert\Range(min: 1, max: 5, notInRangeMessage: 'probabilite_range')]
    private ?int $probabilite = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['risque_prevention:read', 'plan_prevention:read'])]
    private int $niveauRisque = 0;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['risque_prevention:read', 'risque_prevention:write', 'plan_prevention:read'])]
    #[Assert\NotBlank(message: 'mesures_preventives_required')]
    #[Assert\NotNull(message: 'mesures_preventives_required')]
    private ?string $mesuresPreventives = null;

    #[ORM\Column(length: 36, nullable: true)]
    #[Groups(['risque_prevention:read', 'risque_prevention:write', 'plan_prevention:read'])]
    private ?string $tachePlanifieeId = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(['risque_prevention:read', 'risque_prevention:write', 'plan_prevention:read'])]
    private ?int $risqueResiduel = null;

    /**
     * Catégories/sous-catégories de risque sélectionnées (référentiel structuré).
     * `description` reste la représentation texte libre affichée/imprimée.
     */
    #[ORM\ManyToMany(targetEntity: CategorieRisque::class)]
    #[ORM\JoinTable(name: 'risque_prevention_categorie_risque')]
    #[Groups(['risque_prevention:read', 'risque_prevention:write', 'plan_prevention:read'])]
    private Collection $categoriesRisque;

    public function __construct()
    {
        $this->categoriesRisque = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getPlanPrevention(): ?PlanPrevention
    {
        return $this->planPrevention;
    }

    public function setPlanPrevention(?PlanPrevention $planPrevention): static
    {
        $this->planPrevention = $planPrevention;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getGravite(): ?int
    {
        return $this->gravite;
    }

    public function setGravite(int $gravite): static
    {
        $this->gravite = $gravite;

        return $this;
    }

    public function getProbabilite(): ?int
    {
        return $this->probabilite;
    }

    public function setProbabilite(int $probabilite): static
    {
        $this->probabilite = $probabilite;

        return $this;
    }

    public function getNiveauRisque(): int
    {
        return $this->niveauRisque;
    }

    public function getMesuresPreventives(): ?string
    {
        return $this->mesuresPreventives;
    }

    public function setMesuresPreventives(string $mesuresPreventives): static
    {
        $this->mesuresPreventives = $mesuresPreventives;

        return $this;
    }

    public function getTachePlanifieeId(): ?string
    {
        return $this->tachePlanifieeId;
    }

    public function setTachePlanifieeId(?string $tachePlanifieeId): static
    {
        $this->tachePlanifieeId = $tachePlanifieeId;

        return $this;
    }

    public function getRisqueResiduel(): ?int
    {
        return $this->risqueResiduel;
    }

    public function setRisqueResiduel(?int $risqueResiduel): static
    {
        $this->risqueResiduel = $risqueResiduel;

        return $this;
    }

    /** @return Collection<int, CategorieRisque> */
    public function getCategoriesRisque(): Collection
    {
        return $this->categoriesRisque;
    }

    /** @param iterable<CategorieRisque> $categoriesRisque */
    public function setCategoriesRisque(iterable $categoriesRisque): static
    {
        $this->categoriesRisque = $categoriesRisque instanceof Collection
            ? $categoriesRisque
            : new ArrayCollection(is_array($categoriesRisque) ? $categoriesRisque : iterator_to_array($categoriesRisque));

        return $this;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function calculateNiveauRisque(): void
    {
        $this->niveauRisque = ($this->gravite ?? 0) * ($this->probabilite ?? 0);
    }
}
