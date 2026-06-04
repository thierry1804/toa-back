<?php

namespace App\Domain\Permit\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ApiResource]
class PermitHauteur extends Permit
{
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $prestataire = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $region = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descriptionOperation = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $hauteurChute = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $travailToiture = false;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $typePente = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private array $risques = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private array $materiels = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private array $mesuresPrevention = [];

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $planSauvetageDisponible = false;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $secouristePresent = false;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $numerosUrgenceDisponibles = false;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $engagementDemandeur = false;

    public function getPrestataire(): ?string
    {
        return $this->prestataire;
    }

    public function setPrestataire(?string $prestataire): static
    {
        $this->prestataire = $prestataire;
        return $this;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(?string $region): static
    {
        $this->region = $region;
        return $this;
    }

    public function getDescriptionOperation(): ?string
    {
        return $this->descriptionOperation;
    }

    public function setDescriptionOperation(?string $descriptionOperation): static
    {
        $this->descriptionOperation = $descriptionOperation;
        return $this;
    }

    public function getHauteurChute(): ?string
    {
        return $this->hauteurChute;
    }

    public function setHauteurChute(?string $hauteurChute): static
    {
        $this->hauteurChute = $hauteurChute;
        return $this;
    }

    public function isTravailToiture(): ?bool
    {
        return $this->travailToiture;
    }

    public function setTravailToiture(?bool $travailToiture): static
    {
        $this->travailToiture = $travailToiture;
        return $this;
    }

    public function getTypePente(): ?string
    {
        return $this->typePente;
    }

    public function setTypePente(?string $typePente): static
    {
        $this->typePente = $typePente;
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

    public function isPlanSauvetageDisponible(): ?bool
    {
        return $this->planSauvetageDisponible;
    }

    public function setPlanSauvetageDisponible(?bool $planSauvetageDisponible): static
    {
        $this->planSauvetageDisponible = $planSauvetageDisponible;
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
}
