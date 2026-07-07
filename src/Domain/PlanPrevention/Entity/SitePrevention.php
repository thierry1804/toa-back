<?php

declare(strict_types=1);

namespace App\Domain\PlanPrevention\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: '`site_prevention`')]
class SitePrevention
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['site_prevention:read', 'plan_prevention:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: PlanPrevention::class, inversedBy: 'sites')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PlanPrevention $planPrevention = null;

    #[ORM\Column(length: 255)]
    #[Groups(['site_prevention:read', 'plan_prevention:read'])]
    private ?string $nom = null;

    #[ORM\Column(type: Types::FLOAT)]
    #[Groups(['site_prevention:read', 'plan_prevention:read'])]
    private ?float $latitude = null;

    #[ORM\Column(type: Types::FLOAT)]
    #[Groups(['site_prevention:read', 'plan_prevention:read'])]
    private ?float $longitude = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['site_prevention:read', 'plan_prevention:read'])]
    private bool $sourceKmz = false;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['site_prevention:read', 'plan_prevention:read'])]
    private ?string $adresse = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Groups(['site_prevention:read', 'plan_prevention:read'])]
    private ?float $altitude = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['site_prevention:read', 'plan_prevention:read'])]
    private ?string $description = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['site_prevention:read', 'plan_prevention:read'])]
    private ?string $couleurMarqueur = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    #[Groups(['site_prevention:read', 'plan_prevention:read'])]
    private int $ordreAffichage = 0;

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

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(float $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(float $longitude): static
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function isSourceKmz(): bool
    {
        return $this->sourceKmz;
    }

    public function setSourceKmz(bool $sourceKmz): static
    {
        $this->sourceKmz = $sourceKmz;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getAltitude(): ?float
    {
        return $this->altitude;
    }

    public function setAltitude(?float $altitude): static
    {
        $this->altitude = $altitude;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getCouleurMarqueur(): ?string
    {
        return $this->couleurMarqueur;
    }

    public function setCouleurMarqueur(?string $couleurMarqueur): static
    {
        $this->couleurMarqueur = $couleurMarqueur;

        return $this;
    }

    public function getOrdreAffichage(): int
    {
        return $this->ordreAffichage;
    }

    public function setOrdreAffichage(int $ordreAffichage): static
    {
        $this->ordreAffichage = $ordreAffichage;

        return $this;
    }
}
