<?php

declare(strict_types=1);

namespace App\Domain\PlanPrevention\Entity;

use App\Domain\PlanPrevention\Enum\StatutPlanPreventionPdf;
use App\Domain\PlanPrevention\Repository\PlanPreventionPdfRepository;
use App\Domain\User\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PlanPreventionPdfRepository::class)]
#[ORM\Table(name: '`plan_prevention_pdf`')]
class PlanPreventionPdf
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: PlanPrevention::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE', unique: true)]
    private ?PlanPrevention $planPrevention = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $filePath = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $generePar = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $genereAt = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $tailleFichier = null;

    #[ORM\Column(length: 36)]
    private string $jobId;

    #[ORM\Column(length: 20, enumType: StatutPlanPreventionPdf::class)]
    private StatutPlanPreventionPdf $statut = StatutPlanPreventionPdf::EN_COURS;

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

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(?string $filePath): static
    {
        $this->filePath = $filePath;

        return $this;
    }

    public function getGenerePar(): ?User
    {
        return $this->generePar;
    }

    public function setGenerePar(?User $generePar): static
    {
        $this->generePar = $generePar;

        return $this;
    }

    public function getGenereAt(): ?\DateTimeImmutable
    {
        return $this->genereAt;
    }

    public function setGenereAt(?\DateTimeImmutable $genereAt): static
    {
        $this->genereAt = $genereAt;

        return $this;
    }

    public function getTailleFichier(): ?int
    {
        return $this->tailleFichier;
    }

    public function setTailleFichier(?int $tailleFichier): static
    {
        $this->tailleFichier = $tailleFichier;

        return $this;
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }

    public function setJobId(string $jobId): static
    {
        $this->jobId = $jobId;

        return $this;
    }

    public function getStatut(): StatutPlanPreventionPdf
    {
        return $this->statut;
    }

    public function setStatut(StatutPlanPreventionPdf $statut): static
    {
        $this->statut = $statut;

        return $this;
    }
}
