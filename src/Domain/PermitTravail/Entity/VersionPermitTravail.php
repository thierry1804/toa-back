<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Entity;

use App\Domain\PermitTravail\Repository\VersionPermitTravailRepository;
use App\Domain\User\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: VersionPermitTravailRepository::class)]
#[ORM\Table(name: '`version_permit_travail`')]
class VersionPermitTravail
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['permit_travail:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: PermitTravail::class, inversedBy: 'versions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PermitTravail $permitTravail = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['permit_travail:read'])]
    private int $numeroVersion = 1;

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['permit_travail:read'])]
    private array $snapshotData = [];

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['permit_travail:read'])]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['permit_travail:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['permit_travail:read'])]
    private ?string $motifResoumission = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getPermitTravail(): ?PermitTravail
    {
        return $this->permitTravail;
    }

    public function setPermitTravail(?PermitTravail $permitTravail): static
    {
        $this->permitTravail = $permitTravail;

        return $this;
    }

    public function getNumeroVersion(): int
    {
        return $this->numeroVersion;
    }

    public function setNumeroVersion(int $numeroVersion): static
    {
        $this->numeroVersion = $numeroVersion;

        return $this;
    }

    public function getSnapshotData(): array
    {
        return $this->snapshotData;
    }

    public function setSnapshotData(array $snapshotData): static
    {
        $this->snapshotData = $snapshotData;

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

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getMotifResoumission(): ?string
    {
        return $this->motifResoumission;
    }

    public function setMotifResoumission(?string $motifResoumission): static
    {
        $this->motifResoumission = $motifResoumission;

        return $this;
    }
}
