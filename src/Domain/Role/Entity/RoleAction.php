<?php

declare(strict_types=1);

namespace App\Domain\Role\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Domain\Role\Repository\RoleActionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RoleActionRepository::class)]
#[ORM\Table(name: '`role_action`')]
#[ORM\UniqueConstraint(columns: ['role_name', 'action_key'])]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/role-actions',
            security: "is_granted('ROLE_SUPER_ADMIN')",
        ),
        new Post(
            uriTemplate: '/role-actions',
            security: "is_granted('ROLE_SUPER_ADMIN')",
        ),
        new Get(
            uriTemplate: '/role-actions/{id}',
            security: "is_granted('ROLE_SUPER_ADMIN')",
        ),
        new Patch(
            uriTemplate: '/role-actions/{id}',
            security: "is_granted('ROLE_SUPER_ADMIN')",
        ),
        new Delete(
            uriTemplate: '/role-actions/{id}',
            security: "is_granted('ROLE_SUPER_ADMIN')",
        ),
    ],
    normalizationContext: ['groups' => ['role_action:read']],
    denormalizationContext: ['groups' => ['role_action:write']],
)]
class RoleAction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['role_action:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Groups(['role_action:read', 'role_action:write'])]
    #[Assert\NotBlank(message: 'role_action_role_required')]
    private string $roleName;

    #[ORM\Column(name: 'action_key', length: 150)]
    #[Groups(['role_action:read', 'role_action:write'])]
    #[Assert\NotBlank(message: 'role_action_key_required')]
    private string $actionKey;

    /**
     * true = role bypasses ownership check (admin-level access).
     * false = ownership check enforced when ActionKey.ownershipField is set.
     */
    #[ORM\Column]
    #[Groups(['role_action:read', 'role_action:write'])]
    private bool $bypassOwnership = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRoleName(): string
    {
        return $this->roleName;
    }

    public function setRoleName(string $roleName): static
    {
        $this->roleName = $roleName;

        return $this;
    }

    public function getActionKey(): string
    {
        return $this->actionKey;
    }

    public function setActionKey(string $actionKey): static
    {
        $this->actionKey = $actionKey;

        return $this;
    }

    public function isBypassOwnership(): bool
    {
        return $this->bypassOwnership;
    }

    public function setBypassOwnership(bool $bypassOwnership): static
    {
        $this->bypassOwnership = $bypassOwnership;

        return $this;
    }
}
