<?php

namespace App\Domain\ActivityPlanning\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: '`activity_planning_audit`')]
class ActivityPlanningAudit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['activity_planning:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ActivityPlanning::class, inversedBy: 'auditLogs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ActivityPlanning $planning = null;

    #[ORM\Column(length: 255)]
    #[Groups(['activity_planning:read'])]
    private string $fieldName;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['activity_planning:read'])]
    private ?string $oldValue = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['activity_planning:read'])]
    private ?string $newValue = null;

    #[ORM\Column(length: 255)]
    #[Groups(['activity_planning:read'])]
    private string $changedBy;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['activity_planning:read'])]
    private \DateTimeImmutable $changedAt;

    public function __construct(
        string $fieldName,
        ?string $oldValue,
        ?string $newValue,
        string $changedBy
    ) {
        $this->fieldName = $fieldName;
        $this->oldValue = $oldValue;
        $this->newValue = $newValue;
        $this->changedBy = $changedBy;
        $this->changedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlanning(): ?ActivityPlanning
    {
        return $this->planning;
    }

    public function setPlanning(?ActivityPlanning $planning): static
    {
        $this->planning = $planning;

        return $this;
    }

    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    public function getOldValue(): ?string
    {
        return $this->oldValue;
    }

    public function getNewValue(): ?string
    {
        return $this->newValue;
    }

    public function getChangedBy(): string
    {
        return $this->changedBy;
    }

    public function getChangedAt(): \DateTimeImmutable
    {
        return $this->changedAt;
    }
}
