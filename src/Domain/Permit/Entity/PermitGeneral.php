<?php

namespace App\Domain\Permit\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ApiResource]
class PermitGeneral extends Permit
{
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $intituleTravaux = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $localisation = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contractant = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $dureeMaxJours = 30;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private array $travauxRisques = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $permisAnnexes = [];

    // Engagements spécifiques au permis général
    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $evaluationRisquesValidee = false;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $personneCompetenteAssignee = false;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $mesuresPreventionMisesEnPlace = false;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $personnelInforme = false;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $dangersControles = false;

    public function getIntituleTravaux(): ?string
    {
        return $this->intituleTravaux;
    }

    public function setIntituleTravaux(?string $intituleTravaux): static
    {
        $this->intituleTravaux = $intituleTravaux;
        return $this;
    }

    public function getLocalisation(): ?string
    {
        return $this->localisation;
    }

    public function setLocalisation(?string $localisation): static
    {
        $this->localisation = $localisation;
        return $this;
    }

    public function getContractant(): ?string
    {
        return $this->contractant;
    }

    public function setContractant(?string $contractant): static
    {
        $this->contractant = $contractant;
        return $this;
    }

    public function getDureeMaxJours(): ?int
    {
        return $this->dureeMaxJours;
    }

    public function setDureeMaxJours(?int $dureeMaxJours): static
    {
        $this->dureeMaxJours = $dureeMaxJours;
        return $this;
    }

    public function getTravauxRisques(): array
    {
        return $this->travauxRisques;
    }

    public function setTravauxRisques(array $travauxRisques): static
    {
        $this->travauxRisques = $travauxRisques;
        return $this;
    }

    public function getPermisAnnexes(): ?array
    {
        return $this->permisAnnexes;
    }

    public function setPermisAnnexes(?array $permisAnnexes): static
    {
        $this->permisAnnexes = $permisAnnexes;
        return $this;
    }

    public function isEvaluationRisquesValidee(): ?bool
    {
        return $this->evaluationRisquesValidee;
    }

    public function setEvaluationRisquesValidee(?bool $evaluationRisquesValidee): static
    {
        $this->evaluationRisquesValidee = $evaluationRisquesValidee;
        return $this;
    }

    public function isPersonneCompetenteAssignee(): ?bool
    {
        return $this->personneCompetenteAssignee;
    }

    public function setPersonneCompetenteAssignee(?bool $personneCompetenteAssignee): static
    {
        $this->personneCompetenteAssignee = $personneCompetenteAssignee;
        return $this;
    }

    public function isMesuresPreventionMisesEnPlace(): ?bool
    {
        return $this->mesuresPreventionMisesEnPlace;
    }

    public function setMesuresPreventionMisesEnPlace(?bool $mesuresPreventionMisesEnPlace): static
    {
        $this->mesuresPreventionMisesEnPlace = $mesuresPreventionMisesEnPlace;
        return $this;
    }

    public function isPersonnelInforme(): ?bool
    {
        return $this->personnelInforme;
    }

    public function setPersonnelInforme(?bool $personnelInforme): static
    {
        $this->personnelInforme = $personnelInforme;
        return $this;
    }

    public function isDangersControles(): ?bool
    {
        return $this->dangersControles;
    }

    public function setDangersControles(?bool $dangersControles): static
    {
        $this->dangersControles = $dangersControles;
        return $this;
    }
}
