<?php

namespace App\Domain\ActivityPlanning\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Api\Processor\ActivityPlanningUpdateProcessor;
use App\Domain\ActivityPlanning\Validator\CoherentDates;
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
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ACTIVITY_PLANNING_VIEW')"),
        new Post(security: "is_granted('ACTIVITY_PLANNING_CREATE')"),
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

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['activity_planning:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['activity_planning:read', 'activity_planning:write'])]
    #[Assert\NotBlank]
    #[Assert\NotNull]
    private ?string $process = null;

    #[ORM\Column(length: 255)]
    #[Groups(['activity_planning:read', 'activity_planning:write'])]
    #[Assert\NotBlank]
    #[Assert\NotNull]
    private ?string $provider = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['activity_planning:read', 'activity_planning:write'])]
    private ?string $providerEmail = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['activity_planning:read', 'activity_planning:write'])]
    #[Assert\NotBlank]
    #[Assert\NotNull]
    private ?string $projectDescription = null;

    #[ORM\Column(length: 255)]
    #[Groups(['activity_planning:read', 'activity_planning:write'])]
    #[Assert\NotBlank]
    #[Assert\NotNull]
    private ?string $siteCode = null;

    #[ORM\Column(length: 255)]
    #[Groups(['activity_planning:read', 'activity_planning:write'])]
    #[Assert\NotBlank]
    #[Assert\NotNull]
    private ?string $siteNumber = null;

    #[ORM\Column(length: 255)]
    #[Groups(['activity_planning:read', 'activity_planning:write'])]
    #[Assert\NotBlank]
    #[Assert\NotNull]
    private ?string $siteName = null;

    #[ORM\Column(length: 255)]
    #[Groups(['activity_planning:read', 'activity_planning:write'])]
    #[Assert\NotBlank]
    #[Assert\NotNull]
    private ?string $region = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['activity_planning:read', 'activity_planning:write'])]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $theoreticalStartDate = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['activity_planning:read', 'activity_planning:write'])]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $expectedStartDate = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['activity_planning:read', 'activity_planning:write'])]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $expectedEndDate = null;

    #[ORM\Column(length: 50)]
    #[Groups(['activity_planning:read', 'activity_planning:write'])]
    private string $status = self::STATUS_BROUILLON;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['activity_planning:read', 'activity_planning:write'])]
    private ?string $permitReference = null;

    #[ORM\Column]
    #[Groups(['activity_planning:read', 'activity_planning:write'])]
    private bool $permitValidated = false;

    #[ORM\OneToMany(
        targetEntity: ActivityPlanningAudit::class,
        mappedBy: 'planning',
        cascade: ['persist'],
        orphanRemoval: true
    )]
    #[Groups(['activity_planning:read'])]
    private Collection $auditLogs;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['activity_planning:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['activity_planning:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->auditLogs = new ArrayCollection();
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

    public function getProjectDescription(): ?string
    {
        return $this->projectDescription;
    }

    public function setProjectDescription(string $projectDescription): static
    {
        $this->projectDescription = $projectDescription;

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

    public function getSiteNumber(): ?string
    {
        return $this->siteNumber;
    }

    public function setSiteNumber(string $siteNumber): static
    {
        $this->siteNumber = $siteNumber;

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

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(string $region): static
    {
        $this->region = $region;

        return $this;
    }

    public function getTheoreticalStartDate(): ?\DateTimeImmutable
    {
        return $this->theoreticalStartDate;
    }

    public function setTheoreticalStartDate(\DateTimeImmutable $theoreticalStartDate): static
    {
        $this->theoreticalStartDate = $theoreticalStartDate;

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

    public function getAuditLogs(): Collection
    {
        return $this->auditLogs;
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

    public function isLocked(): bool
    {
        return in_array($this->status, [self::STATUS_EN_COURS, self::STATUS_VALIDE], true);
    }

    public function getLockedFields(): array
    {
        $fields = [];

        if ($this->isLocked()) {
            $fields = array_merge($fields, [
                'process', 'siteCode', 'siteNumber', 'siteName', 'region',
                'theoreticalStartDate', 'expectedStartDate', 'expectedEndDate',
            ]);
        }

        if ($this->permitValidated) {
            $fields = array_merge($fields, [
                'provider', 'projectDescription',
            ]);
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
            'projectDescription' => $this->projectDescription,
            'siteCode' => $this->siteCode,
            'siteNumber' => $this->siteNumber,
            'siteName' => $this->siteName,
            'region' => $this->region,
            'theoreticalStartDate' => $this->theoreticalStartDate?->format('c'),
            'expectedStartDate' => $this->expectedStartDate?->format('c'),
            'expectedEndDate' => $this->expectedEndDate?->format('c'),
            'status' => $this->status,
            'permitReference' => $this->permitReference,
            'permitValidated' => $this->permitValidated,
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
