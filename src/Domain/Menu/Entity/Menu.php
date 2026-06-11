<?php

namespace App\Domain\Menu\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: '`menu`')]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('MENU_VIEW')",
            normalizationContext: ['groups' => ['menu:read', 'menu:tree']],
        ),
        new Post(security: "is_granted('MENU_CREATE')"),
        new Get(security: "is_granted('MENU_VIEW', object)"),
        new Put(security: "is_granted('MENU_EDIT', object)"),
        new Patch(security: "is_granted('MENU_EDIT', object)"),
        new Delete(security: "is_granted('MENU_DELETE', object)"),
    ],
    normalizationContext: ['groups' => ['menu:read']],
    denormalizationContext: ['groups' => ['menu:write']],
)]
class Menu
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['menu:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['menu:read', 'menu:write'])]
    #[Assert\NotBlank(message: 'menu_name_required')]
    private string $name;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['menu:read', 'menu:write'])]
    private ?string $icon = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['menu:read', 'menu:write'])]
    private ?string $route = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Groups(['menu:write'])]
    private ?Menu $parent = null;

    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    #[Groups(['menu:read', 'menu:tree'])]
    private Collection $children;

    #[ORM\Column(type: 'integer')]
    #[Groups(['menu:read', 'menu:write'])]
    #[Assert\NotNull(message: 'menu_position_required')]
    private int $position = 0;

    #[ORM\Column]
    #[Groups(['menu:read', 'menu:write'])]
    private bool $isActive = true;

    #[ORM\OneToMany(targetEntity: MenuAccess::class, mappedBy: 'menu', cascade: ['remove'])]
    private Collection $accessRules;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->accessRules = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function setRoute(?string $route): static
    {
        $this->route = $route;

        return $this;
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

    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(self $child): static
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }

        return $this;
    }

    public function removeChild(self $child): static
    {
        if ($this->children->removeElement($child)) {
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getAccessRules(): Collection
    {
        return $this->accessRules;
    }
}
