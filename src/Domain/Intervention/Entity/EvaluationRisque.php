<?php

declare(strict_types=1);

namespace App\Domain\Intervention\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use App\Api\Processor\EvaluationRisqueUpdateProcessor;
use App\Domain\Intervention\Enum\NiveauCouleurRisque;
use App\Domain\Intervention\Repository\EvaluationRisqueRepository;
use App\Domain\PlanPrevention\Entity\RisquePrevention;
use App\Domain\User\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EvaluationRisqueRepository::class)]
#[ORM\Table(name: '`evaluation_risque`')]
#[ORM\Index(columns: ['intervention_id', 'est_reevalue'], name: 'idx_eval_risque_reevalue')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/interventions/{interventionId}/risques',
            uriVariables: [
                'interventionId' => new Link(
                    fromClass: Intervention::class,
                    toProperty: 'intervention',
                ),
            ],
            security: "is_granted('INTERVENTION_VIEW')",
            name: 'evaluation_risque_list',
        ),
        new Patch(
            uriTemplate: '/interventions/{interventionId}/risques/{id}',
            uriVariables: [
                'interventionId' => new Link(
                    fromClass: Intervention::class,
                    toProperty: 'intervention',
                ),
                'id' => new Link(fromClass: EvaluationRisque::class),
            ],
            security: "is_granted('INTERVENTION_EDIT', object.getIntervention())",
            processor: EvaluationRisqueUpdateProcessor::class,
            name: 'evaluation_risque_update',
        ),
    ],
    normalizationContext: ['groups' => ['evaluation_risque:read']],
    denormalizationContext: ['groups' => ['evaluation_risque:write']],
)]
class EvaluationRisque
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['evaluation_risque:read', 'intervention:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Intervention::class, inversedBy: 'evaluations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Intervention $intervention = null;

    #[ORM\ManyToOne(targetEntity: RisquePrevention::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['evaluation_risque:read', 'intervention:read'])]
    private ?RisquePrevention $risqueSource = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['evaluation_risque:read', 'evaluation_risque:write', 'intervention:read'])]
    #[Assert\NotBlank(message: 'description_required')]
    private ?string $description = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['evaluation_risque:read', 'evaluation_risque:write', 'intervention:read'])]
    #[Assert\NotNull(message: 'gravite_required')]
    #[Assert\Range(min: 1, max: 5, notInRangeMessage: 'gravite_range')]
    private ?int $gravite = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['evaluation_risque:read', 'evaluation_risque:write', 'intervention:read'])]
    #[Assert\NotNull(message: 'probabilite_required')]
    #[Assert\Range(min: 1, max: 5, notInRangeMessage: 'probabilite_range')]
    private ?int $probabilite = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['evaluation_risque:read', 'intervention:read'])]
    private int $niveauRisque = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['evaluation_risque:read', 'evaluation_risque:write', 'intervention:read'])]
    private ?string $mesuresConfirmees = null;

    #[ORM\Column]
    #[Groups(['evaluation_risque:read', 'intervention:read'])]
    private bool $estReevalue = false;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['evaluation_risque:read', 'intervention:read'])]
    private ?User $createdBy = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getIntervention(): ?Intervention
    {
        return $this->intervention;
    }

    public function setIntervention(?Intervention $intervention): static
    {
        $this->intervention = $intervention;

        return $this;
    }

    public function getRisqueSource(): ?RisquePrevention
    {
        return $this->risqueSource;
    }

    public function setRisqueSource(?RisquePrevention $risqueSource): static
    {
        $this->risqueSource = $risqueSource;

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

    #[Groups(['evaluation_risque:read', 'intervention:read'])]
    public function getNiveauCouleur(): NiveauCouleurRisque
    {
        return NiveauCouleurRisque::fromNiveau($this->niveauRisque);
    }

    public function getMesuresConfirmees(): ?string
    {
        return $this->mesuresConfirmees;
    }

    public function setMesuresConfirmees(?string $mesuresConfirmees): static
    {
        $this->mesuresConfirmees = $mesuresConfirmees;

        return $this;
    }

    public function isEstReevalue(): bool
    {
        return $this->estReevalue;
    }

    public function setEstReevalue(bool $estReevalue): static
    {
        $this->estReevalue = $estReevalue;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function calculateNiveauRisque(): void
    {
        $this->niveauRisque = ($this->gravite ?? 0) * ($this->probabilite ?? 0);
    }
}
