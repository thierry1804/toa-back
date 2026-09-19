<?php

declare(strict_types=1);

namespace App\Domain\PlanPrevention\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Api\Processor\KmzImportProcessor;
use App\Api\Processor\PlanPreventionCreateProcessor;
use App\Api\Processor\PlanPreventionExaminerProcessor;
use App\Api\Processor\PlanPreventionRefuserProcessor;
use App\Api\Processor\PlanPreventionResoumettreProcessor;
use App\Api\Processor\PlanPreventionSoumettreProcessor;
use App\Api\Processor\PlanPreventionValiderProcessor;
use App\Domain\PlanPrevention\Entity\DecisionHsePlanPrevention;
use App\Domain\PlanPrevention\Entity\ExamenPlanPrevention;
use App\Domain\PlanPrevention\Entity\VersionPlanPrevention;
use App\Domain\PlanPrevention\Enum\StatutPlanPrevention;
use App\Domain\PlanPrevention\Repository\PlanPreventionRepository;
use App\Domain\User\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PlanPreventionRepository::class)]
#[ORM\Table(name: '`plan_prevention`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/plans-prevention',
            security: "is_granted('PLAN_PREVENTION_VIEW')",
            order: ['createdAt' => 'DESC'],
        ),
        new Post(
            uriTemplate: '/plans-prevention',
            security: "is_granted('PLAN_PREVENTION_CREATE')",
            processor: PlanPreventionCreateProcessor::class,
        ),
        new Get(
            uriTemplate: '/plans-prevention/{id}',
            requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'],
            security: "is_granted('PLAN_PREVENTION_VIEW', object)",
        ),
        new Patch(
            uriTemplate: '/plans-prevention/{id}',
            requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'],
            security: "is_granted('PLAN_PREVENTION_EDIT', object)",
        ),
        new Post(
            uriTemplate: '/plans-prevention/{id}/soumettre',
            read: true,
            deserialize: false,
            security: "is_granted('PLAN_PREVENTION_SUBMIT', object)",
            processor: PlanPreventionSoumettreProcessor::class,
            name: 'plan_prevention_soumettre',
        ),
        new Post(
            uriTemplate: '/plans-prevention/{id}/examiner',
            read: true,
            deserialize: false,
            security: "is_granted('PLAN_PREVENTION_EXAMINE', object)",
            processor: PlanPreventionExaminerProcessor::class,
            name: 'plan_prevention_examiner',
        ),
        new Post(
            uriTemplate: '/plans-prevention/{id}/valider',
            read: true,
            deserialize: false,
            security: "is_granted('PLAN_PREVENTION_VALIDER_HSE', object)",
            processor: PlanPreventionValiderProcessor::class,
            name: 'plan_prevention_valider',
        ),
        new Post(
            uriTemplate: '/plans-prevention/{id}/refuser',
            read: true,
            deserialize: false,
            security: "is_granted('PLAN_PREVENTION_REFUSER_HSE', object)",
            processor: PlanPreventionRefuserProcessor::class,
            name: 'plan_prevention_refuser',
        ),
        new Post(
            uriTemplate: '/plans-prevention/{id}/resoumettre',
            read: true,
            deserialize: false,
            security: "is_granted('PLAN_PREVENTION_RESOUMETTRE', object)",
            processor: PlanPreventionResoumettreProcessor::class,
            name: 'plan_prevention_resoumettre',
        ),
        new Post(
            uriTemplate: '/plans-prevention/{id}/import-kmz',
            read: true,
            deserialize: false,
            security: "is_granted('PLAN_PREVENTION_IMPORT_KMZ', object)",
            processor: KmzImportProcessor::class,
            name: 'plan_prevention_import_kmz',
        ),
    ],
    normalizationContext: ['groups' => ['plan_prevention:read']],
    denormalizationContext: ['groups' => ['plan_prevention:write']],
)]
class PlanPrevention
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['plan_prevention:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Groups(['plan_prevention:read'])]
    private ?string $reference = null;

    #[ORM\Column(length: 100)]
    #[Groups(['plan_prevention:read', 'plan_prevention:write'])]
    #[Assert\NotBlank(message: 'code_site_required')]
    #[Assert\NotNull(message: 'code_site_required')]
    private ?string $codeSite = null;

    #[ORM\Column(length: 255)]
    #[Groups(['plan_prevention:read', 'plan_prevention:write'])]
    #[Assert\NotBlank(message: 'localite_required')]
    #[Assert\NotNull(message: 'localite_required')]
    private ?string $localite = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['plan_prevention:read', 'plan_prevention:write'])]
    #[Assert\NotBlank(message: 'activite_planifiee_required')]
    #[Assert\NotNull(message: 'activite_planifiee_required')]
    private ?string $activitePlanifiee = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['plan_prevention:read', 'plan_prevention:write'])]
    #[Assert\NotNull(message: 'date_debut_required')]
    private ?\DateTimeImmutable $dateDebut = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['plan_prevention:read', 'plan_prevention:write'])]
    #[Assert\NotNull(message: 'date_fin_required')]
    private ?\DateTimeImmutable $dateFin = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['plan_prevention:read'])]
    private ?string $referenceActivite = null;

    #[ORM\Column(length: 50, enumType: StatutPlanPrevention::class)]
    #[Groups(['plan_prevention:read'])]
    private StatutPlanPrevention $statut = StatutPlanPrevention::BROUILLON;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['plan_prevention:read'])]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['plan_prevention:read', 'plan_prevention:write'])]
    private ?User $chefProjet = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['plan_prevention:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['plan_prevention:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(
        targetEntity: RisquePrevention::class,
        mappedBy: 'planPrevention',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
        fetch: 'EXTRA_LAZY',
    )]
    #[Groups(['plan_prevention:read'])]
    private Collection $risques;

    /** R-04 : sections/modes opératoires saisis par le prestataire — voir SectionPrevention. */
    #[ORM\OneToMany(
        targetEntity: SectionPrevention::class,
        mappedBy: 'planPrevention',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
        fetch: 'EXTRA_LAZY',
    )]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    #[Groups(['plan_prevention:read'])]
    private Collection $sections;

    #[ORM\OneToMany(
        targetEntity: DocumentPrevention::class,
        mappedBy: 'planPrevention',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
        fetch: 'EXTRA_LAZY',
    )]
    #[Groups(['plan_prevention:read'])]
    private Collection $documents;

    #[ORM\OneToMany(
        targetEntity: SitePrevention::class,
        mappedBy: 'planPrevention',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
        fetch: 'EXTRA_LAZY',
    )]
    #[Groups(['plan_prevention:read'])]
    private Collection $sites;

    #[ORM\OneToMany(
        targetEntity: ExamenPlanPrevention::class,
        mappedBy: 'planPrevention',
        cascade: ['remove'],
        fetch: 'EXTRA_LAZY',
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['examineAt' => 'ASC'])]
    #[Groups(['plan_prevention:read'])]
    private Collection $examens;

    #[ORM\OneToMany(
        targetEntity: DecisionHsePlanPrevention::class,
        mappedBy: 'planPrevention',
        fetch: 'EXTRA_LAZY',
    )]
    #[Groups(['plan_prevention:read'])]
    private Collection $decisionsHse;

    #[ORM\OneToMany(
        targetEntity: VersionPlanPrevention::class,
        mappedBy: 'planPrevention',
        cascade: ['remove'],
        fetch: 'EXTRA_LAZY',
        orphanRemoval: false,
    )]
    #[ORM\OrderBy(['numeroVersion' => 'ASC'])]
    #[Groups(['plan_prevention:read'])]
    private Collection $versions;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['plan_prevention:read', 'plan_prevention:write'])]
    private array $installations = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['plan_prevention:read', 'plan_prevention:write'])]
    private array $equipements = [];

    #[ORM\Column(nullable: true)]
    #[Groups(['plan_prevention:read', 'plan_prevention:write'])]
    private ?int $planificationId = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['plan_prevention:read'])]
    private ?string $typeIntervention = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['plan_prevention:read'])]
    private ?string $process = null;

    #[Groups(['plan_prevention:read'])]
    private array $planificationSites = [];

    #[Groups(['plan_prevention:read'])]
    private array $planificationSections = [];

    public function __construct()
    {
        $this->risques       = new ArrayCollection();
        $this->sections      = new ArrayCollection();
        $this->documents     = new ArrayCollection();
        $this->sites         = new ArrayCollection();
        $this->examens       = new ArrayCollection();
        $this->decisionsHse  = new ArrayCollection();
        $this->versions      = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getCodeSite(): ?string
    {
        return $this->codeSite;
    }

    public function setCodeSite(string $codeSite): static
    {
        $this->codeSite = $codeSite;

        return $this;
    }

    public function getLocalite(): ?string
    {
        return $this->localite;
    }

    public function setLocalite(string $localite): static
    {
        $this->localite = $localite;

        return $this;
    }

    public function getActivitePlanifiee(): ?string
    {
        return $this->activitePlanifiee;
    }

    public function setActivitePlanifiee(string $activitePlanifiee): static
    {
        $this->activitePlanifiee = $activitePlanifiee;

        return $this;
    }

    public function getDateDebut(): ?\DateTimeImmutable
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTimeImmutable $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): ?\DateTimeImmutable
    {
        return $this->dateFin;
    }

    public function setDateFin(\DateTimeImmutable $dateFin): static
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    public function getReferenceActivite(): ?string
    {
        return $this->referenceActivite;
    }

    public function setReferenceActivite(?string $referenceActivite): static
    {
        $this->referenceActivite = $referenceActivite;

        return $this;
    }

    public function getStatut(): StatutPlanPrevention
    {
        return $this->statut;
    }

    public function setStatut(StatutPlanPrevention $statut): static
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

    public function getChefProjet(): ?User
    {
        return $this->chefProjet;
    }

    public function setChefProjet(?User $chefProjet): static
    {
        $this->chefProjet = $chefProjet;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getRisques(): Collection
    {
        return $this->risques;
    }

    public function addRisque(RisquePrevention $risque): static
    {
        if (!$this->risques->contains($risque)) {
            $this->risques->add($risque);
            $risque->setPlanPrevention($this);
        }

        return $this;
    }

    public function getSections(): Collection
    {
        return $this->sections;
    }

    public function addSection(SectionPrevention $section): static
    {
        if (!$this->sections->contains($section)) {
            $this->sections->add($section);
            $section->setPlanPrevention($this);
        }

        return $this;
    }

    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(DocumentPrevention $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setPlanPrevention($this);
        }

        return $this;
    }

    public function getSites(): Collection
    {
        return $this->sites;
    }

    public function addSite(SitePrevention $site): static
    {
        if (!$this->sites->contains($site)) {
            $this->sites->add($site);
            $site->setPlanPrevention($this);
        }

        return $this;
    }

    public function getExamens(): Collection
    {
        return $this->examens;
    }

    public function getDecisionsHse(): Collection
    {
        return $this->decisionsHse;
    }

    public function getVersions(): Collection
    {
        return $this->versions;
    }

    public function getInstallations(): array
    {
        return $this->installations ?? [];
    }

    public function setInstallations(array $installations): static
    {
        $this->installations = $installations;

        return $this;
    }

    public function getEquipements(): array
    {
        return $this->equipements ?? [];
    }

    public function setEquipements(array $equipements): static
    {
        $this->equipements = $equipements;

        return $this;
    }

    public function getPlanificationId(): ?int
    {
        return $this->planificationId;
    }

    public function setPlanificationId(?int $planificationId): static
    {
        $this->planificationId = $planificationId;

        return $this;
    }

    public function getTypeIntervention(): ?string
    {
        return $this->typeIntervention;
    }

    public function setTypeIntervention(?string $typeIntervention): static
    {
        $this->typeIntervention = $typeIntervention;

        return $this;
    }

    public function getProcess(): ?string
    {
        return $this->process;
    }

    public function setProcess(?string $process): static
    {
        $this->process = $process;

        return $this;
    }

    public function getPlanificationSites(): array
    {
        return $this->planificationSites;
    }

    public function setPlanificationSites(array $sites): static
    {
        $this->planificationSites = $sites;

        return $this;
    }

    public function getPlanificationSections(): array
    {
        return $this->planificationSections;
    }

    public function setPlanificationSections(array $sections): static
    {
        $this->planificationSections = $sections;

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if (null === $this->createdAt) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
