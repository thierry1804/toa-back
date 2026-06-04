<?php

namespace App\Domain\Permit\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ApiResource]
class PermitElectrique extends Permit
{
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private array $typeTravail = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private array $tension = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $typeCircuitEquipement = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descriptionTravail = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $raisonNonMiseHorsTension = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private array $risques = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private array $materiels = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private array $mesuresPrevention = [];

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $secouristePresent = false;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $numerosUrgenceDisponibles = false;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $engagementDemandeur = false;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $bonConsignation = null;

    public function getTypeTravail(): array
    {
        return $this->typeTravail;
    }

    public function setTypeTravail(array $typeTravail): static
    {
        $this->typeTravail = $typeTravail;
        return $this;
    }

    public function getTension(): array
    {
        return $this->tension;
    }

    public function setTension(array $tension): static
    {
        $this->tension = $tension;
        return $this;
    }

    public function getTypeCircuitEquipement(): ?string
    {
        return $this->typeCircuitEquipement;
    }

    public function setTypeCircuitEquipement(?string $typeCircuitEquipement): static
    {
        $this->typeCircuitEquipement = $typeCircuitEquipement;
        return $this;
    }

    public function getDescriptionTravail(): ?string
    {
        return $this->descriptionTravail;
    }

    public function setDescriptionTravail(?string $descriptionTravail): static
    {
        $this->descriptionTravail = $descriptionTravail;
        return $this;
    }

    public function getRaisonNonMiseHorsTension(): ?string
    {
        return $this->raisonNonMiseHorsTension;
    }

    public function setRaisonNonMiseHorsTension(?string $raisonNonMiseHorsTension): static
    {
        $this->raisonNonMiseHorsTension = $raisonNonMiseHorsTension;
        return $this;
    }

    public function getRisques(): array
    {
        return $this->risques;
    }

    public function setRisques(array $risques): static
    {
        $this->risques = $risques;
        return $this;
    }

    public function getMateriels(): array
    {
        return $this->materiels;
    }

    public function setMateriels(array $materiels): static
    {
        $this->materiels = $materiels;
        return $this;
    }

    public function getMesuresPrevention(): array
    {
        return $this->mesuresPrevention;
    }

    public function setMesuresPrevention(array $mesuresPrevention): static
    {
        $this->mesuresPrevention = $mesuresPrevention;
        return $this;
    }

    public function isSecouristePresent(): ?bool
    {
        return $this->secouristePresent;
    }

    public function setSecouristePresent(?bool $secouristePresent): static
    {
        $this->secouristePresent = $secouristePresent;
        return $this;
    }

    public function isNumerosUrgenceDisponibles(): ?bool
    {
        return $this->numerosUrgenceDisponibles;
    }

    public function setNumerosUrgenceDisponibles(?bool $numerosUrgenceDisponibles): static
    {
        $this->numerosUrgenceDisponibles = $numerosUrgenceDisponibles;
        return $this;
    }

    public function isEngagementDemandeur(): ?bool
    {
        return $this->engagementDemandeur;
    }

    public function setEngagementDemandeur(?bool $engagementDemandeur): static
    {
        $this->engagementDemandeur = $engagementDemandeur;
        return $this;
    }

    public function getBonConsignation(): ?array
    {
        return $this->bonConsignation;
    }

    public function setBonConsignation(?array $bonConsignation): static
    {
        $this->bonConsignation = $bonConsignation;
        return $this;
    }
}
