<?php

namespace App\Domain\Permit\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
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
        new Put(security: "is_granted('PERMIT_EDIT', object)"),
        new Patch(security: "is_granted('PERMIT_EDIT', object)"),
        new Delete(security: "is_granted('PERMIT_DELETE', object)")
    ]
)]
abstract class Permit
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    protected ?Uuid $id = null;

    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    protected ?Uuid $planPreventionId = null;

    #[ORM\Column(length: 255, nullable: true)]
    protected ?string $planPreventionReference = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le code site est requis')]
    protected ?string $codeSite = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\GreaterThanOrEqual(1)]
    protected ?int $nombreIntervenants = 1;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    protected ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    protected ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(length: 50)]
    protected ?string $status = 'en_attente_validation_chef';

    #[ORM\Column(length: 255, nullable: true)]
    protected ?string $demandeurNom = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    protected ?\DateTimeInterface $demandeurDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    protected ?string $superviseurNom = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    protected ?\DateTimeInterface $superviseurDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    protected ?string $creerPar = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    protected ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }
}
