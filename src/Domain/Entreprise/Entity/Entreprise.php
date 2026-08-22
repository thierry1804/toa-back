<?php

declare(strict_types=1);

namespace App\Domain\Entreprise\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Domain\Entreprise\Repository\EntrepriseRepository;
use App\Domain\Entreprise\Validator\UniqueEntrepriseNom;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EntrepriseRepository::class)]
#[ORM\Table(name: 'entreprise')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntrepriseNom]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['entreprise:read']],
    denormalizationContext: ['groups' => ['entreprise:write']],
)]
class Entreprise
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['entreprise:read', 'user:read', 'activity_planning:read', 'plan_prevention:read', 'permit_travail:read', 'intervention:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Groups(['entreprise:read', 'entreprise:write', 'user:read', 'activity_planning:read', 'plan_prevention:read', 'permit_travail:read', 'intervention:read'])]
    #[Assert\NotBlank(message: 'entreprise_nom_required')]
    #[Assert\Length(max: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['entreprise:read', 'entreprise:write'])]
    private ?string $numeroRegistreCommerce = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['entreprise:read', 'entreprise:write'])]
    private ?string $siegeSocial = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['entreprise:read', 'entreprise:write'])]
    #[Assert\Length(max: 100)]
    private ?string $qualiteRepresentant = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['entreprise:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['entreprise:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?Uuid
    {
        return $this->id;
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

    public function getNumeroRegistreCommerce(): ?string
    {
        return $this->numeroRegistreCommerce;
    }

    public function setNumeroRegistreCommerce(?string $numeroRegistreCommerce): static
    {
        $this->numeroRegistreCommerce = $numeroRegistreCommerce;

        return $this;
    }

    public function getSiegeSocial(): ?string
    {
        return $this->siegeSocial;
    }

    public function setSiegeSocial(?string $siegeSocial): static
    {
        $this->siegeSocial = $siegeSocial;

        return $this;
    }

    public function getQualiteRepresentant(): ?string
    {
        return $this->qualiteRepresentant;
    }

    public function setQualiteRepresentant(?string $qualiteRepresentant): static
    {
        $this->qualiteRepresentant = $qualiteRepresentant;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
