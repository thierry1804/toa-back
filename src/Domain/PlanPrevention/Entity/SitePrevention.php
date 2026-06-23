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
}
