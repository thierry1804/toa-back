<?php

declare(strict_types=1);

namespace App\Domain\PlanPrevention\Entity;

use App\Domain\PlanPrevention\Repository\ExamenPlanPreventionRepository;
use App\Domain\User\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ExamenPlanPreventionRepository::class)]
#[ORM\Table(name: '`examen_plan_prevention`')]
class ExamenPlanPrevention
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['plan_prevention:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: PlanPrevention::class, inversedBy: 'examens')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PlanPrevention $planPrevention = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['plan_prevention:read'])]
    private ?User $examinePar = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['plan_prevention:read'])]
    private ?\DateTimeImmutable $examineAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['plan_prevention:read'])]
    private ?string $commentaire = null;

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

    public function getExaminePar(): ?User
    {
        return $this->examinePar;
    }

    public function setExaminePar(?User $examinePar): static
    {
        $this->examinePar = $examinePar;

        return $this;
    }

    public function getExamineAt(): ?\DateTimeImmutable
    {
        return $this->examineAt;
    }

    public function setExamineAt(\DateTimeImmutable $examineAt): static
    {
        $this->examineAt = $examineAt;

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
}
