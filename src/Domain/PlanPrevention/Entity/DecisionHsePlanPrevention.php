<?php

declare(strict_types=1);

namespace App\Domain\PlanPrevention\Entity;

use App\Domain\PlanPrevention\Enum\DecisionHse;
use App\Domain\PlanPrevention\Repository\DecisionHsePlanPreventionRepository;
use App\Domain\User\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DecisionHsePlanPreventionRepository::class)]
#[ORM\Table(name: '`decision_hse_plan_prevention`')]
class DecisionHsePlanPrevention
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['plan_prevention:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: PlanPrevention::class, inversedBy: 'decisionsHse')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PlanPrevention $planPrevention = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['plan_prevention:read'])]
    private ?User $decidePar = null;

    #[ORM\Column(length: 10, enumType: DecisionHse::class)]
    #[Groups(['plan_prevention:read'])]
    private ?DecisionHse $decision = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['plan_prevention:read'])]
    private ?string $commentaire = null;

    #[ORM\Column(length: 64)]
    #[Groups(['plan_prevention:read'])]
    private ?string $signatureElectronique = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['plan_prevention:read'])]
    private ?\DateTimeImmutable $decidedAt = null;

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

    public function getDecidePar(): ?User
    {
        return $this->decidePar;
    }

    public function setDecidePar(?User $decidePar): static
    {
        $this->decidePar = $decidePar;

        return $this;
    }

    public function getDecision(): ?DecisionHse
    {
        return $this->decision;
    }

    public function setDecision(DecisionHse $decision): static
    {
        $this->decision = $decision;

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

    public function getSignatureElectronique(): ?string
    {
        return $this->signatureElectronique;
    }

    public function setSignatureElectronique(string $signatureElectronique): static
    {
        $this->signatureElectronique = $signatureElectronique;

        return $this;
    }

    public function getDecidedAt(): ?\DateTimeImmutable
    {
        return $this->decidedAt;
    }

    public function setDecidedAt(\DateTimeImmutable $decidedAt): static
    {
        $this->decidedAt = $decidedAt;

        return $this;
    }
}
