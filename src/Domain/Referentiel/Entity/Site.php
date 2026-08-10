<?php

declare(strict_types=1);

namespace App\Domain\Referentiel\Entity;

use App\Domain\Referentiel\Repository\SiteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: SiteRepository::class)]
#[ORM\Table(name: '`site`')]
class Site
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['site:read', 'import_kmz_site:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(name: 'code_site', length: 255)]
    #[Groups(['site:read', 'import_kmz_site:read'])]
    private string $codeSite = '';

    #[ORM\Column(name: 'nom_site', length: 255)]
    #[Groups(['site:read', 'import_kmz_site:read'])]
    private string $nomSite = '';

    #[ORM\Column(type: Types::FLOAT)]
    #[Groups(['site:read'])]
    private float $latitude = 0.0;

    #[ORM\Column(type: Types::FLOAT)]
    #[Groups(['site:read'])]
    private float $longitude = 0.0;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Groups(['site:read'])]
    private ?float $altitude = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['site:read'])]
    private ?string $description = null;

    #[ORM\Column(name: 'couleur_marqueur', length: 20, nullable: true)]
    #[Groups(['site:read'])]
    private ?string $couleurMarqueur = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['site:read'])]
    private ?string $region = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['site:read'])]
    private ?string $commune = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['site:read'])]
    private ?string $district = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['site:read'])]
    private ?string $fokontany = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['site:read'])]
    private ?string $situation = null;

    #[ORM\Column(name: 'type_site', length: 100, nullable: true)]
    #[Groups(['site:read', 'import_kmz_site:read'])]
    private ?string $typeSite = null;

    #[ORM\Column(name: 'zone', length: 100, nullable: true)]
    #[Groups(['site:read', 'import_kmz_site:read'])]
    private ?string $zone = null;

    #[ORM\Column(name: 'type_pylone', length: 100, nullable: true)]
    #[Groups(['site:read', 'import_kmz_site:read'])]
    private ?string $typePylone = null;

    #[ORM\Column(name: 'hauteur_pylone', type: Types::FLOAT, nullable: true)]
    #[Groups(['site:read', 'import_kmz_site:read'])]
    private ?float $hauteurPylone = null;

    #[ORM\Column(name: 'source_kmz', type: Types::BOOLEAN)]
    #[Groups(['site:read'])]
    private bool $sourceKmz = false;

    #[ORM\ManyToOne(targetEntity: ImportKmzSite::class, inversedBy: 'sites')]
    #[ORM\JoinColumn(name: 'import_kmz_id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['site:read'])]
    private ?ImportKmzSite $importKmz = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    #[Groups(['site:read'])]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE)]
    #[Groups(['site:read'])]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $now            = new \DateTime();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

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

    public function getNomSite(): string
    {
        return $this->nomSite;
    }

    public function setNomSite(string $nomSite): static
    {
        $this->nomSite = $nomSite;

        return $this;
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function setLatitude(float $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    public function setLongitude(float $longitude): static
    {
        $this->longitude = $longitude;

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

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(?string $region): static
    {
        $this->region = $region;

        return $this;
    }

    public function getCommune(): ?string
    {
        return $this->commune;
    }

    public function setCommune(?string $commune): static
    {
        $this->commune = $commune;

        return $this;
    }

    public function getDistrict(): ?string
    {
        return $this->district;
    }

    public function setDistrict(?string $district): static
    {
        $this->district = $district;

        return $this;
    }

    public function getFokontany(): ?string
    {
        return $this->fokontany;
    }

    public function setFokontany(?string $fokontany): static
    {
        $this->fokontany = $fokontany;

        return $this;
    }

    public function getSituation(): ?string
    {
        return $this->situation;
    }

    public function setSituation(?string $situation): static
    {
        $this->situation = $situation;

        return $this;
    }

    public function getTypeSite(): ?string
    {
        return $this->typeSite;
    }

    public function setTypeSite(?string $typeSite): static
    {
        $this->typeSite = $typeSite;

        return $this;
    }

    public function getZone(): ?string
    {
        return $this->zone;
    }

    public function setZone(?string $zone): static
    {
        $this->zone = $zone;

        return $this;
    }

    public function getTypePylone(): ?string
    {
        return $this->typePylone;
    }

    public function setTypePylone(?string $typePylone): static
    {
        $this->typePylone = $typePylone;

        return $this;
    }

    public function getHauteurPylone(): ?float
    {
        return $this->hauteurPylone;
    }

    public function setHauteurPylone(?float $hauteurPylone): static
    {
        $this->hauteurPylone = $hauteurPylone;

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

    public function getImportKmz(): ?ImportKmzSite
    {
        return $this->importKmz;
    }

    public function setImportKmz(?ImportKmzSite $importKmz): static
    {
        $this->importKmz = $importKmz;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
