<?php

declare(strict_types=1);

namespace App\Domain\PlanPrevention\Entity;

use App\Domain\PlanPrevention\Repository\VersionPlanPreventionRepository;
use App\Domain\User\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: VersionPlanPreventionRepository::class)]
#[ORM\Table(name: '`version_plan_prevention`')]
class VersionPlanPrevention
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['plan_prevention:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: PlanPrevention::class, inversedBy: 'versions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PlanPrevention $planPrevention = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['plan_prevention:read'])]
    private int $numeroVersion = 1;

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['plan_prevention:read'])]
    private array $snapshotData = [];

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['plan_prevention:read'])]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['plan_prevention:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['plan_prevention:read'])]
    private ?string $motifResoumission = null;

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
