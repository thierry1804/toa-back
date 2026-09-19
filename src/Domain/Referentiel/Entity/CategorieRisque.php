<?php

declare(strict_types=1);

namespace App\Domain\Referentiel\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Domain\Referentiel\Repository\CategorieRisqueRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CategorieRisqueRepository::class)]
#[ORM\Table(name: '`categorie_risque`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/referentiel/categories-risque',
            security: "is_granted('CATEGORIE_RISQUE_VIEW')",
        ),
        new Post(
            uriTemplate: '/referentiel/categories-risque',
            security: "is_granted('CATEGORIE_RISQUE_CREATE')",
        ),
        new Get(
            uriTemplate: '/referentiel/categories-risque/{id}',
            security: "is_granted('CATEGORIE_RISQUE_VIEW')",
        ),
        new Patch(
            uriTemplate: '/referentiel/categories-risque/{id}',
            security: "is_granted('CATEGORIE_RISQUE_EDIT', object)",
        ),
        new Delete(
            uriTemplate: '/referentiel/categories-risque/{id}',
            security: "is_granted('CATEGORIE_RISQUE_DELETE', object)",
        ),
    ],
    normalizationContext: ['groups' => ['categorie_risque:read']],
    denormalizationContext: ['groups' => ['categorie_risque:write']],
)]
class CategorieRisque
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['categorie_risque:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Groups(['categorie_risque:read', 'categorie_risque:write'])]
    #[Assert\NotBlank(message: 'nom_required')]
    #[Assert\Length(max: 255, maxMessage: 'nom_too_long')]
    private ?string $nom = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['categorie_risque:read', 'categorie_risque:write'])]
    private ?string $typePermis = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Groups(['categorie_risque:read', 'categorie_risque:write'])]
    #[Assert\Choice(choices: ['APN', 'API'], message: 'type_site_invalid')]
    private ?string $typeSite = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['categorie_risque:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['categorie_risque:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['categorie_risque:read', 'categorie_risque:write'])]
    private ?self $parent = null;

    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['nom' => 'ASC'])]
    #[Groups(['categorie_risque:read'])]
    private Collection $children;

    public function __construct()
    {
        $this->children = new ArrayCollection();
    }

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

    public function getTypePermis(): ?string
    {
        return $this->typePermis;
    }

    public function setTypePermis(?string $typePermis): static
    {
        $this->typePermis = $typePermis;

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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /** @return Collection<int, self> */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if (null === $this->createdAt) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
