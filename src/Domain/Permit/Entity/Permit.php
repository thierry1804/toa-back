<?php

namespace App\Domain\Permit\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\Api\Processor\PermitUpdateProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: '`permit`')]
#[ORM\HasLifecycleCallbacks]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'string', length: 30)]
#[ORM\DiscriminatorMap([
    'general' => PermitGeneral::class,
    'electrique' => PermitElectrique::class,
    'hauteur' => PermitHauteur::class
])]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(security: "is_granted('PERMIT_VIEW', object)"),
        new Post(),
        new Put(
            security: "is_granted('PERMIT_EDIT', object)",
            processor: PermitUpdateProcessor::class
        ),
        new Patch(
            security: "is_granted('PERMIT_EDIT', object)",
            processor: PermitUpdateProcessor::class
        ),
        new Delete(security: "is_granted('PERMIT_DELETE', object)")
    ],
    normalizationContext: ['groups' => ['permit:read']],
    denormalizationContext: ['groups' => ['permit:write']],
)]
abstract class Permit
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['permit:read'])]
    protected ?Uuid $id = null;

    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    #[Groups(['permit:read', 'permit:write'])]
    protected ?Uuid $planPreventionId = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['permit:read', 'permit:write'])]
    protected ?string $planPreventionReference = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'site_code_required')]
    #[Groups(['permit:read', 'permit:write'])]
    protected ?string $codeSite = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\GreaterThanOrEqual(1)]
    #[Groups(['permit:read', 'permit:write'])]
    protected ?int $nombreIntervenants = 1;

    // "Début prévisionnel" — previously "Début prévu"
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['permit:read', 'permit:write'])]
    protected ?\DateTimeInterface $dateDebut = null;

    // "Fin prévisionnelle" — previously "Fin prévue"
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['permit:read', 'permit:write'])]
    protected ?\DateTimeInterface $dateFin = null;

    // Auto-set on transition → in_progress; never writable via API.
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['permit:read'])]
    protected ?\DateTimeInterface $actualStartDate = null;

    // Auto-set on transition → closed/rejected/expired; never writable via API.
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['permit:read'])]
    protected ?\DateTimeInterface $actualEndDate = null;

    #[ORM\Column(length: 50)]
    #[Groups(['permit:read', 'permit:write'])]
    protected ?string $status = 'en_attente_validation_chef';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['permit:read', 'permit:write'])]
    protected ?string $demandeurNom = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['permit:read', 'permit:write'])]
    protected ?\DateTimeInterface $demandeurDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['permit:read', 'permit:write'])]
    protected ?string $superviseurNom = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['permit:read', 'permit:write'])]
    protected ?\DateTimeInterface $superviseurDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['permit:read', 'permit:write'])]
    protected ?string $creerPar = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['permit:read'])]
    protected ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['permit:read'])]
    protected ?\DateTimeInterface $updatedAt = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    // Getters and Setters

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getPlanPreventionId(): ?Uuid
    {
        return $this->planPreventionId;
    }

    public function setPlanPreventionId(?Uuid $planPreventionId): static
    {
        $this->planPreventionId = $planPreventionId;
        return $this;
    }

    public function getPlanPreventionReference(): ?string
    {
        return $this->planPreventionReference;
    }

    public function setPlanPreventionReference(?string $planPreventionReference): static
    {
        $this->planPreventionReference = $planPreventionReference;
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

    public function getNombreIntervenants(): ?int
    {
        return $this->nombreIntervenants;
    }

    public function setNombreIntervenants(int $nombreIntervenants): static
    {
        $this->nombreIntervenants = $nombreIntervenants;
        return $this;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeInterface $dateDebut): static
    {
        $this->dateDebut = $dateDebut;
        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeInterface $dateFin): static
    {
        $this->dateFin = $dateFin;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getDemandeurNom(): ?string
    {
        return $this->demandeurNom;
    }

    public function setDemandeurNom(?string $demandeurNom): static
    {
        $this->demandeurNom = $demandeurNom;
        return $this;
    }

    public function getDemandeurDate(): ?\DateTimeInterface
    {
        return $this->demandeurDate;
    }

    public function setDemandeurDate(?\DateTimeInterface $demandeurDate): static
    {
        $this->demandeurDate = $demandeurDate;
        return $this;
    }

    public function getSuperviseurNom(): ?string
    {
        return $this->superviseurNom;
    }

    public function setSuperviseurNom(?string $superviseurNom): static
    {
        $this->superviseurNom = $superviseurNom;
        return $this;
    }

    public function getSuperviseurDate(): ?\DateTimeInterface
    {
        return $this->superviseurDate;
    }

    public function setSuperviseurDate(?\DateTimeInterface $superviseurDate): static
    {
        $this->superviseurDate = $superviseurDate;
        return $this;
    }

    public function getCreerPar(): ?string
    {
        return $this->creerPar;
    }

    public function setCreerPar(?string $creerPar): static
    {
        $this->creerPar = $creerPar;
        return $this;
    }

    public function getActualStartDate(): ?\DateTimeInterface
    {
        return $this->actualStartDate;
    }

    public function setActualStartDate(?\DateTimeInterface $actualStartDate): static
    {
        $this->actualStartDate = $actualStartDate;
        return $this;
    }

    public function getActualEndDate(): ?\DateTimeInterface
    {
        return $this->actualEndDate;
    }

    public function setActualEndDate(?\DateTimeInterface $actualEndDate): static
    {
        $this->actualEndDate = $actualEndDate;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }
}
