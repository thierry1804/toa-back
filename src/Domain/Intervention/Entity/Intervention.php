<?php

declare(strict_types=1);

namespace App\Domain\Intervention\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Api\Processor\EvaluationRisqueValiderProcessor;
use App\Api\Processor\InterventionCreateProcessor;
use App\Domain\Intervention\Enum\StatutIntervention;
use App\Domain\Intervention\Repository\InterventionRepository;
use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\User\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use App\Domain\Intervention\Entity\SuiviJournalier;

#[ORM\Entity(repositoryClass: InterventionRepository::class)]
#[ORM\Table(name: '`intervention`')]
#[ORM\UniqueConstraint(name: 'uq_intervention_permit', columns: ['permit_travail_id'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/interventions',
            security: "is_granted('INTERVENTION_VIEW', null)",
        ),
        new Post(
            uriTemplate: '/interventions',
            security: "is_granted('INTERVENTION_CREATE')",
            processor: InterventionCreateProcessor::class,
        ),
        new Get(
            uriTemplate: '/interventions/{id}',
            requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'],
            security: "is_granted('INTERVENTION_VIEW', object)",
        ),
        new Post(
            uriTemplate: '/interventions/{id}/valider-evaluation',
            read: true,
            deserialize: false,
            security: "is_granted('INTERVENTION_VALIDER', object)",
            processor: EvaluationRisqueValiderProcessor::class,
            name: 'intervention_valider_evaluation',
        ),
    ],
    normalizationContext: ['groups' => ['intervention:read']],
    denormalizationContext: ['groups' => ['intervention:write']],
)]
class Intervention
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['intervention:read'])]
    private ?Uuid $id = null;

    #[ORM\OneToOne(targetEntity: PermitTravail::class, inversedBy: 'intervention')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['intervention:read', 'intervention:write'])]
    private ?PermitTravail $permitTravail = null;

    #[ORM\Column(length: 50, enumType: StatutIntervention::class)]
    #[Groups(['intervention:read'])]
    private StatutIntervention $statut = StatutIntervention::EN_PREPARATION;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['intervention:read'])]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['intervention:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(
        targetEntity: EvaluationRisque::class,
        mappedBy: 'intervention',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
        fetch: 'EXTRA_LAZY',
    )]
    #[Groups(['intervention:read'])]
    private Collection $evaluations;

    #[ORM\OneToMany(
        targetEntity: SuiviJournalier::class,
        mappedBy: 'intervention',
        cascade: ['remove'],
        orphanRemoval: true,
        fetch: 'EXTRA_LAZY',
    )]
    private Collection $suivis;

    public function __construct()
    {
        $this->evaluations = new ArrayCollection();
        $this->suivis = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getPermitTravail(): ?PermitTravail
    {
        return $this->permitTravail;
    }

    #[Groups(['intervention:read'])]
    public function getPermitTravailId(): ?string
    {
        return $this->permitTravail?->getId()?->toRfc4122();
    }

    public function setPermitTravail(?PermitTravail $permitTravail): static
    {
        $this->permitTravail = $permitTravail;

        return $this;
    }

    public function getStatut(): StatutIntervention
    {
        return $this->statut;
    }

    public function setStatut(StatutIntervention $statut): static
    {
        $this->statut = $statut;

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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getEvaluations(): Collection
    {
        return $this->evaluations;
    }

    public function getSuivis(): Collection
    {
        return $this->suivis;
    }

    public function addEvaluation(EvaluationRisque $evaluation): static
    {
        if (!$this->evaluations->contains($evaluation)) {
            $this->evaluations->add($evaluation);
            $evaluation->setIntervention($this);
        }

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if (null === $this->createdAt) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }
}
