<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Entity;

use App\Domain\PermitTravail\Repository\KpiInterventionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: KpiInterventionRepository::class)]
#[ORM\Table(name: 'kpi_intervention')]
#[ORM\UniqueConstraint(name: 'UNIQ_KPI_SITE_PERIODE', columns: ['code_site', 'periode'])]
class KpiIntervention
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 50)]
    private string $codeSite = '';

    #[ORM\Column(length: 7)]
    private string $periode = '';

    #[ORM\Column(type: Types::INTEGER)]
    private int $nbPermisCloturesValides = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $nbPermisCloturesTotal = 0;

    #[ORM\Column(type: Types::FLOAT)]
    private float $tauxCloture = 0.0;

    #[ORM\Column(type: Types::FLOAT)]
    private float $delaiMoyenValidationCdp = 0.0;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $tauxIncidents = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $tempsMoyenValidationPlan = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $tempsMoyenValidationPermis = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $tempsMoyenValidationPv = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $avancementMoyen = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getCodeSite(): string
    {
        return $this->codeSite;
    }

    public function setCodeSite(string $codeSite): static
    {
        $this->codeSite = $codeSite;

        return $this;
    }

    public function getPeriode(): string
    {
        return $this->periode;
    }

    public function setPeriode(string $periode): static
    {
        $this->periode = $periode;

        return $this;
    }

    public function getNbPermisCloturesValides(): int
    {
        return $this->nbPermisCloturesValides;
    }

    public function setNbPermisCloturesValides(int $nb): static
    {
        $this->nbPermisCloturesValides = $nb;

        return $this;
    }

    public function getNbPermisCloturesTotal(): int
    {
        return $this->nbPermisCloturesTotal;
    }

    public function setNbPermisCloturesTotal(int $nb): static
    {
        $this->nbPermisCloturesTotal = $nb;

        return $this;
    }

    public function getTauxCloture(): float
    {
        return $this->tauxCloture;
    }

    public function setTauxCloture(float $taux): static
    {
        $this->tauxCloture = $taux;

        return $this;
    }

    public function getDelaiMoyenValidationCdp(): float
    {
        return $this->delaiMoyenValidationCdp;
    }

    public function setDelaiMoyenValidationCdp(float $delai): static
    {
        $this->delaiMoyenValidationCdp = $delai;

        return $this;
    }

    public function getTauxIncidents(): ?float
    {
        return $this->tauxIncidents;
    }

    public function setTauxIncidents(?float $taux): static
    {
        $this->tauxIncidents = $taux;

        return $this;
    }

    public function getTempsMoyenValidationPlan(): ?float
    {
        return $this->tempsMoyenValidationPlan;
    }

    public function setTempsMoyenValidationPlan(?float $temps): static
    {
        $this->tempsMoyenValidationPlan = $temps;

        return $this;
    }

    public function getTempsMoyenValidationPermis(): ?float
    {
        return $this->tempsMoyenValidationPermis;
    }

    public function setTempsMoyenValidationPermis(?float $temps): static
    {
        $this->tempsMoyenValidationPermis = $temps;

        return $this;
    }

    public function getTempsMoyenValidationPv(): ?float
    {
        return $this->tempsMoyenValidationPv;
    }

    public function setTempsMoyenValidationPv(?float $temps): static
    {
        $this->tempsMoyenValidationPv = $temps;

        return $this;
    }

    public function getAvancementMoyen(): ?float
    {
        return $this->avancementMoyen;
    }

    public function setAvancementMoyen(?float $avancement): static
    {
        $this->avancementMoyen = $avancement;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
