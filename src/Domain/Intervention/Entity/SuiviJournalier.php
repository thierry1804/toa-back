<?php

declare(strict_types=1);

namespace App\Domain\Intervention\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Api\Processor\SuiviJournalierCreateProcessor;
use App\Api\Processor\SuiviJournalierUpdateProcessor;
use App\Domain\Intervention\Repository\SuiviJournalierRepository;
use App\Domain\User\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Context;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SuiviJournalierRepository::class)]
#[ORM\Table(name: '`suivi_journalier`')]
#[ORM\UniqueConstraint(name: 'uq_suivi_intervention_date', columns: ['intervention_id', 'date'])]
#[ORM\Index(columns: ['intervention_id', 'date'], name: 'idx_suivi_intervention_date')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/interventions/{interventionId}/suivis',
            uriVariables: [
                'interventionId' => new Link(
                    fromClass: Intervention::class,
                    toProperty: 'intervention',
                ),
            ],
            security: "is_granted('SUIVI_JOURNALIER_VIEW', null)",
            name: 'suivi_journalier_list',
        ),
        new Post(
            uriTemplate: '/suivis-journaliers',
            security: "is_granted('SUIVI_JOURNALIER_CREATE')",
            processor: SuiviJournalierCreateProcessor::class,
            denormalizationContext: ['groups' => ['suivi_journalier:write', 'suivi_journalier:create']],
            name: 'suivi_journalier_create',
        ),
        new Get(
            uriTemplate: '/suivis-journaliers/{id}',
            requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'],
            security: "is_granted('SUIVI_JOURNALIER_VIEW', object)",
        ),
        new Patch(
            uriTemplate: '/suivis-journaliers/{id}',
            requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'],
            security: "is_granted('SUIVI_JOURNALIER_EDIT', object)",
            processor: SuiviJournalierUpdateProcessor::class,
            name: 'suivi_journalier_update',
        ),
    ],
    normalizationContext: ['groups' => ['suivi_journalier:read']],
    denormalizationContext: ['groups' => ['suivi_journalier:write']],
)]
class SuiviJournalier
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['suivi_journalier:read', 'intervention:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Intervention::class, inversedBy: 'suivis')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['suivi_journalier:write', 'suivi_journalier:create'])]
    private ?Intervention $intervention = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    #[Groups(['suivi_journalier:read', 'suivi_journalier:create', 'intervention:read'])]
    #[Assert\NotNull(message: 'suivi_journalier.date_required')]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column(length: 150)]
    #[Groups(['suivi_journalier:read', 'suivi_journalier:write', 'intervention:read'])]
    #[Assert\NotBlank(message: 'suivi_journalier.nom_responsable_required')]
    #[Assert\Length(max: 150, maxMessage: 'suivi_journalier.nom_responsable_too_long')]
    private ?string $nomResponsable = null;

    #[ORM\Column]
    #[Groups(['suivi_journalier:read', 'suivi_journalier:write', 'intervention:read'])]
    #[Assert\NotNull(message: 'suivi_journalier.realise_required')]
    private ?bool $realise = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['suivi_journalier:read', 'suivi_journalier:write', 'intervention:read'])]
    private ?string $motifNonRealisation = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['suivi_journalier:read', 'suivi_journalier:write', 'intervention:read'])]
    #[Assert\NotNull(message: 'suivi_journalier.avancement_required')]
    #[Assert\Range(min: 0, max: 100, notInRangeMessage: 'suivi_journalier.avancement_range')]
    private ?int $avancementPourcentage = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['suivi_journalier:read', 'suivi_journalier:write', 'intervention:read'])]
    private ?string $commentaire = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['suivi_journalier:read', 'intervention:read'])]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['suivi_journalier:read', 'intervention:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(
        targetEntity: SuiviJournalierDocument::class,
        mappedBy: 'suiviJournalier',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
        fetch: 'EXTRA_LAZY',
    )]
    #[Groups(['suivi_journalier:read'])]
    private Collection $documents;

    public function __construct()
    {
        $this->documents = new ArrayCollection();
    }

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

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getNomResponsable(): ?string
    {
        return $this->nomResponsable;
    }

    public function setNomResponsable(string $nomResponsable): static
    {
        $this->nomResponsable = $nomResponsable;

        return $this;
    }

    public function isRealise(): ?bool
    {
        return $this->realise;
    }

    public function setRealise(bool $realise): static
    {
        $this->realise = $realise;

        return $this;
    }

    public function getMotifNonRealisation(): ?string
    {
        return $this->motifNonRealisation;
    }

    public function setMotifNonRealisation(?string $motifNonRealisation): static
    {
        $this->motifNonRealisation = $motifNonRealisation;

        return $this;
    }

    public function getAvancementPourcentage(): ?int
    {
        return $this->avancementPourcentage;
    }

    public function setAvancementPourcentage(int $avancementPourcentage): static
    {
        $this->avancementPourcentage = $avancementPourcentage;

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;

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

    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if (null === $this->createdAt) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }
}
