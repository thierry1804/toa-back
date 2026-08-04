<?php

namespace App\Domain\ActivityPlanning\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Api\Processor\ActivityPlanningCreateProcessor;
use App\Api\Processor\ActivityPlanningUpdateProcessor;
use App\Domain\ActivityPlanning\Validator\CoherentDates;
use App\Domain\User\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: '`activity_planning`')]
#[ORM\HasLifecycleCallbacks]
#[CoherentDates]
#[ApiFilter(OrderFilter::class, properties: ['id', 'createdAt', 'expectedStartDate', 'status'])]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ACTIVITY_PLANNING_VIEW')"),
        new Post(security: "is_granted('ACTIVITY_PLANNING_CREATE')", processor: ActivityPlanningCreateProcessor::class),
        new Get(security: "is_granted('ACTIVITY_PLANNING_VIEW', object)"),
        new Put(
            security: "is_granted('ACTIVITY_PLANNING_EDIT', object)",
            processor: ActivityPlanningUpdateProcessor::class
        ),
        new Patch(
            security: "is_granted('ACTIVITY_PLANNING_EDIT', object)",
            processor: ActivityPlanningUpdateProcessor::class
        ),
        new Delete(security: "is_granted('ACTIVITY_PLANNING_DELETE', object)"),
    ],
    normalizationContext: ['groups' => ['activity_planning:read']],
    denormalizationContext: ['groups' => ['activity_planning:write']],
)]
class ActivityPlanning
{
    public const STATUS_BROUILLON = 'brouillon';
    public const STATUS_PLANIFIE = 'planifie';
    public const STATUS_EN_COURS = 'en_cours';
    public const STATUS_VALIDE = 'valide';
    public const STATUS_ANNULE = 'annule';
    public const STATUS_STAND_BY = 'stand_by';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['activity_planning:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['activity_planning:read', 'activity_planning:write'])]
    #[Assert\NotBlank(message: 'process_required')]
    #[Assert\NotNull(message: 'process_required')]
    private ?string $process = null;

    #[ORM\Column(length: 255)]
    #[Groups(['activity_planning:read', 'activity_planning:write'])]
    #[Assert\NotBlank(message: 'provider_required')]
    #[Assert\NotNull(message: 'provider_required')]
    private ?string $provider = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['activity_planning:read', 'activity_planning:write'])]
    private ?string $providerEmail = null;

    #[ORM\Column(length: 255)]
    #[Groups(['activity_planning:read', 'activity_planning:write'])]
    #[Assert\NotBlank(message: 'activity_site_code_required')]
    #[Assert\NotNull(message: 'activity_site_code_required')]
    private ?string $siteCode = null;

    #[ORM\Column(length: 255)]
    #[Groups(['activity_planning:read', 'activity_planning:write'])]
    #[Assert\NotBlank(message: 'site_name_required')]
    #[Assert\NotNull(message: 'site_name_required')]
    private ?string $siteName = null;

    // Kept in DB for historical data but no longer required or exposed in write operations.
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['activity_planning:read'])]
    private ?\DateTimeImmutable $theoreticalStartDate = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['activity_planning:read', 'activity_planning:write'])]
    #[Assert\NotNull(message: 'expected_start_date_required')]
    private ?\DateTimeImmutable $expectedStartDate = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['activity_planning:read', 'activity_planning:write'])]
    #[Assert\NotNull(message: 'expected_end_date_required')]
    private ?\DateTimeImmutable $expectedEndDate = null;

    // Auto-set on status transition: en_cours → actualStartDate, valide/annule → actualEndDate.
    // Read-only via API — never in activity_planning:write group.
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['activity_planning:read'])]
    private ?\DateTimeImmutable $actualStartDate = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['activity_planning:read'])]
    private ?\DateTimeImmutable $actualEndDate = null;

    #[ORM\Column(length: 50)]
    #[Groups(['activity_planning:read', 'activity_planning:write'])]
    private string $status = self::STATUS_PLANIFIE;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['activity_planning:read', 'activity_planning:write'])]
    private ?string $permitReference = null;

    #[ORM\Column]
    #[Groups(['activity_planning:read', 'activity_planning:write'])]
    private bool $permitValidated = false;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['activity_planning:read', 'activity_planning:write'])]
    private ?string $typeIntervention = null;

    /** @var list<array{codeSite: string, nomSite: string}>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['activity_planning:read', 'activity_planning:write'])]
    private ?array $sites = null;

    #[ORM\OneToMany(
        targetEntity: ActivityPlanningAudit::class,
        mappedBy: 'planning',
        cascade: ['persist'],
        orphanRemoval: true
    )]
    #[Groups(['activity_planning:read'])]
    private Collection $auditLogs;

    #[ORM\OneToMany(
        targetEntity: SectionPlanifiee::class,
        mappedBy: 'planning',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    #[Groups(['activity_planning:read', 'activity_planning:write'])]
    private Collection $sections;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['activity_planning:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['activity_planning:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['activity_planning:read'])]
    private ?User $createdBy = null;

    public function __construct()
    {
        $this->auditLogs = new ArrayCollection();
        $this->sections  = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProcess(): ?string
    {
        return $this->process;
    }

    public function setProcess(string $process): static
    {
        $this->process = $process;

        return $this;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    public function getProviderEmail(): ?string
    {
        return $this->providerEmail;
    }

    public function setProviderEmail(?string $providerEmail): static
    {
        $this->providerEmail = $providerEmail;

        return $this;
    }

    public function getSiteCode(): ?string
    {
        return $this->siteCode;
    }

    public function setSiteCode(string $siteCode): static
    {
        $this->siteCode = $siteCode;

        return $this;
    }

    public function getSiteName(): ?string
    {
        return $this->siteName;
    }

    public function setSiteName(string $siteName): static
    {
        $this->siteName = $siteName;

        return $this;
    }

    public function getTheoreticalStartDate(): ?\DateTimeImmutable
    {
        return $this->theoreticalStartDate;
    }

    public function setTheoreticalStartDate(?\DateTimeImmutable $theoreticalStartDate): static
    {
        $this->theoreticalStartDate = $theoreticalStartDate;

        return $this;
    }

    public function getActualStartDate(): ?\DateTimeImmutable
    {
        return $this->actualStartDate;
    }

    public function setActualStartDate(?\DateTimeImmutable $actualStartDate): static
    {
        $this->actualStartDate = $actualStartDate;

        return $this;
    }

    public function getActualEndDate(): ?\DateTimeImmutable
    {
        return $this->actualEndDate;
    }

    public function setActualEndDate(?\DateTimeImmutable $actualEndDate): static
    {
        $this->actualEndDate = $actualEndDate;

        return $this;
    }

    public function getExpectedStartDate(): ?\DateTimeImmutable
    {
        return $this->expectedStartDate;
    }

    public function setExpectedStartDate(\DateTimeImmutable $expectedStartDate): static
    {
        $this->expectedStartDate = $expectedStartDate;

        return $this;
    }

    public function getExpectedEndDate(): ?\DateTimeImmutable
    {
        return $this->expectedEndDate;
    }

    public function setExpectedEndDate(\DateTimeImmutable $expectedEndDate): static
    {
        $this->expectedEndDate = $expectedEndDate;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getPermitReference(): ?string
    {
        return $this->permitReference;
    }

    public function setPermitReference(?string $permitReference): static
    {
        $this->permitReference = $permitReference;

        return $this;
    }

    public function isPermitValidated(): bool
    {
        return $this->permitValidated;
    }

    public function setPermitValidated(bool $permitValidated): static
    {
        $this->permitValidated = $permitValidated;

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

    public function getSites(): ?array
    {
        return $this->sites;
    }

    public function setSites(?array $sites): static
    {
        $this->sites = $sites;

        return $this;
    }

    public function getAuditLogs(): Collection
    {
        return $this->auditLogs;
    }

    public function getSections(): Collection
    {
        return $this->sections;
    }

    public function addSection(SectionPlanifiee $section): static
    {
        if (!$this->sections->contains($section)) {
            $this->sections->add($section);
            $section->setPlanning($this);
        }

        return $this;
    }

    public function removeSection(SectionPlanifiee $section): static
    {
        $this->sections->removeElement($section);

        return $this;
    }

    public function addAuditLog(ActivityPlanningAudit $auditLog): static
    {
        if (!$this->auditLogs->contains($auditLog)) {
            $this->auditLogs->add($auditLog);
            $auditLog->setPlanning($this);
        }

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

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function isLocked(): bool
    {
        return in_array($this->status, [self::STATUS_EN_COURS, self::STATUS_VALIDE], true);
    }

    public function getLockedFields(): array
    {
        $fields = [];

        if ($this->isLocked()) {
            $fields = array_merge($fields, [
                'process', 'siteCode', 'siteName',
                'expectedStartDate', 'expectedEndDate',
                'typeIntervention', 'sites',
            ]);
        }

        if ($this->permitValidated) {
            $fields[] = 'provider';
        }

        return array_unique($fields);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'process' => $this->process,
            'provider' => $this->provider,
            'providerEmail' => $this->providerEmail,
            'siteCode' => $this->siteCode,
            'siteName' => $this->siteName,
            'theoreticalStartDate' => $this->theoreticalStartDate?->format('c'),
            'expectedStartDate' => $this->expectedStartDate?->format('c'),
            'expectedEndDate' => $this->expectedEndDate?->format('c'),
            'actualStartDate' => $this->actualStartDate?->format('c'),
            'actualEndDate' => $this->actualEndDate?->format('c'),
            'status' => $this->status,
            'permitReference' => $this->permitReference,
            'permitValidated' => $this->permitValidated,
            'typeIntervention' => $this->typeIntervention,
            'sites' => $this->sites,
        ];
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
