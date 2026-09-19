<?php

declare(strict_types=1);

namespace App\Domain\PlanPrevention\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Api\Processor\SectionPreventionCreateProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * R-04 : saisie des sections déplacée de la planification (ActivityPlanning)
 * vers le plan de prévention, par le prestataire. Remplace SectionPlanifiee
 * pour toute nouvelle saisie — SectionPlanifiee reste en lecture seule
 * (historique des planifications déjà saisies).
 */
#[ORM\Entity]
#[ORM\Table(name: 'section_prevention')]
#[ORM\Index(columns: ['plan_prevention_id', 'ordre'], name: 'idx_section_prevention_plan_ordre')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/plans-prevention/{planPreventionId}/sections',
            uriVariables: [
                'planPreventionId' => new Link(
                    fromClass: PlanPrevention::class,
                    toProperty: 'planPrevention',
                ),
            ],
            read: false,
            security: "is_granted('PLAN_PREVENTION_EDIT_OPERATOIRE')",
            processor: SectionPreventionCreateProcessor::class,
            name: 'section_prevention_create',
        ),
        new Patch(
            uriTemplate: '/plans-prevention/{planPreventionId}/sections/{id}',
            uriVariables: [
                'planPreventionId' => new Link(
                    fromClass: PlanPrevention::class,
                    toProperty: 'planPrevention',
                ),
                'id' => new Link(fromClass: SectionPrevention::class),
            ],
            security: "is_granted('PLAN_PREVENTION_EDIT_OPERATOIRE', object.getPlanPrevention())",
            name: 'section_prevention_update',
        ),
        new Delete(
            uriTemplate: '/plans-prevention/{planPreventionId}/sections/{id}',
            uriVariables: [
                'planPreventionId' => new Link(
                    fromClass: PlanPrevention::class,
                    toProperty: 'planPrevention',
                ),
                'id' => new Link(fromClass: SectionPrevention::class),
            ],
            security: "is_granted('PLAN_PREVENTION_EDIT_OPERATOIRE', object.getPlanPrevention())",
            name: 'section_prevention_delete',
        ),
    ],
    normalizationContext: ['groups' => ['section_prevention:read']],
    denormalizationContext: ['groups' => ['section_prevention:write']],
)]
class SectionPrevention
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['section_prevention:read', 'plan_prevention:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: PlanPrevention::class, inversedBy: 'sections')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PlanPrevention $planPrevention = null;

    #[ORM\Column(length: 150)]
    #[Groups(['section_prevention:read', 'section_prevention:write', 'plan_prevention:read'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    private ?string $libelle = null;

    #[ORM\Column]
    #[Groups(['section_prevention:read', 'section_prevention:write', 'plan_prevention:read'])]
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    private int $ordre = 0;

    #[ORM\OneToMany(
        targetEntity: ModeOperatoire::class,
        mappedBy: 'section',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    #[Groups(['section_prevention:read', 'plan_prevention:read'])]
    private Collection $modesOperatoires;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['section_prevention:read', 'plan_prevention:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->modesOperatoires = new ArrayCollection();
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

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getOrdre(): int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;

        return $this;
    }

    public function getModesOperatoires(): Collection
    {
        return $this->modesOperatoires;
    }

    public function addModeOperatoire(ModeOperatoire $modeOperatoire): static
    {
        if (!$this->modesOperatoires->contains($modeOperatoire)) {
            $this->modesOperatoires->add($modeOperatoire);
            $modeOperatoire->setSection($this);
        }

        return $this;
    }

    public function removeModeOperatoire(ModeOperatoire $modeOperatoire): static
    {
        $this->modesOperatoires->removeElement($modeOperatoire);

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if (null === $this->createdAt) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }
}
