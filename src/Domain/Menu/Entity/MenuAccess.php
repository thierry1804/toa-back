<?php

namespace App\Domain\Menu\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: '`menu_access`')]
#[ORM\UniqueConstraint(columns: ['menu_id', 'role_name'])]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/menu-accesses',
            security: "is_granted('ROLE_SUPER_ADMIN')",
        ),
        new Post(
            uriTemplate: '/menu-accesses',
            security: "is_granted('ROLE_SUPER_ADMIN')",
        ),
        new Get(
            uriTemplate: '/menu-accesses/{id}',
            security: "is_granted('ROLE_SUPER_ADMIN')",
        ),
        new Put(
            uriTemplate: '/menu-accesses/{id}',
            security: "is_granted('ROLE_SUPER_ADMIN')",
        ),
        new Patch(
            uriTemplate: '/menu-accesses/{id}',
            security: "is_granted('ROLE_SUPER_ADMIN')",
        ),
        new Delete(
            uriTemplate: '/menu-accesses/{id}',
            security: "is_granted('ROLE_SUPER_ADMIN')",
        ),
    ],
    normalizationContext: ['groups' => ['menu_access:read']],
    denormalizationContext: ['groups' => ['menu_access:write']],
)]
class MenuAccess
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['menu_access:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Menu::class, inversedBy: 'accessRules')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['menu_access:read', 'menu_access:write'])]
    #[Assert\NotNull(message: 'menu_access_menu_required')]
    private ?Menu $menu = null;

    #[ORM\Column(name: 'role_name', length: 100)]
    #[Groups(['menu_access:read', 'menu_access:write'])]
    #[Assert\NotBlank(message: 'menu_access_role_required')]
    private string $role;

    #[ORM\Column]
    #[Groups(['menu_access:read', 'menu_access:write'])]
    private bool $canView = true;

    #[ORM\Column]
    #[Groups(['menu_access:read', 'menu_access:write'])]
    private bool $canCreate = false;

    #[ORM\Column]
    #[Groups(['menu_access:read', 'menu_access:write'])]
    private bool $canEdit = false;

    #[ORM\Column]
    #[Groups(['menu_access:read', 'menu_access:write'])]
    private bool $canDelete = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMenu(): ?Menu
    {
        return $this->menu;
    }

    public function setMenu(?Menu $menu): static
    {
        $this->menu = $menu;

        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getCanView(): bool
    {
        return $this->canView;
    }

    public function setCanView(bool $canView): static
    {
        $this->canView = $canView;

        return $this;
    }

    public function getCanCreate(): bool
    {
        return $this->canCreate;
    }

    public function setCanCreate(bool $canCreate): static
    {
        $this->canCreate = $canCreate;

        return $this;
    }

    public function getCanEdit(): bool
    {
        return $this->canEdit;
    }

    public function setCanEdit(bool $canEdit): static
    {
        $this->canEdit = $canEdit;

        return $this;
    }

    public function getCanDelete(): bool
    {
        return $this->canDelete;
    }

    public function setCanDelete(bool $canDelete): static
    {
        $this->canDelete = $canDelete;

        return $this;
    }
}
