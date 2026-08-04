<?php

declare(strict_types=1);

namespace App\Domain\Referentiel\Entity;

use App\Domain\Referentiel\Repository\ImportKmzSiteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ImportKmzSiteRepository::class)]
#[ORM\Table(name: '`referentiel_import_kmz_site`')]
class ImportKmzSite
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['import_kmz_site:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['import_kmz_site:read'])]
    private string $nomFichier = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['import_kmz_site:read'])]
    private ?string $originalFilename = null;

    #[ORM\Column]
    #[Groups(['import_kmz_site:read'])]
    private \DateTimeImmutable $importedAt;

    #[ORM\Column(options: ['default' => 0])]
    #[Groups(['import_kmz_site:read'])]
    private int $nombreSites = 0;

    /** @var Collection<int, Site> */
    #[ORM\OneToMany(targetEntity: Site::class, mappedBy: 'importKmz')]
    private Collection $sites;

    public function __construct()
    {
        $this->importedAt = new \DateTimeImmutable();
        $this->sites = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getNomFichier(): string
    {
        return $this->nomFichier;
    }

    public function setNomFichier(string $nomFichier): static
    {
        $this->nomFichier = $nomFichier;

        return $this;
    }

    public function getOriginalFilename(): ?string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(?string $originalFilename): static
    {
        $this->originalFilename = $originalFilename;

        return $this;
    }

    public function getImportedAt(): \DateTimeImmutable
    {
        return $this->importedAt;
    }

    public function setImportedAt(\DateTimeImmutable $importedAt): static
    {
        $this->importedAt = $importedAt;

        return $this;
    }

    public function getNombreSites(): int
    {
        return $this->nombreSites;
    }

    public function setNombreSites(int $nombreSites): static
    {
        $this->nombreSites = $nombreSites;

        return $this;
    }

    /** @return Collection<int, Site> */
    public function getSites(): Collection
    {
        return $this->sites;
    }
}
