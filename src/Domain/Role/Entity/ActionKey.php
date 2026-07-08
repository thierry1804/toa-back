<?php

declare(strict_types=1);

namespace App\Domain\Role\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use App\Domain\Role\Repository\ActionKeyRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ActionKeyRepository::class)]
#[ORM\Table(name: '`action_key`')]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/action-keys',
            security: "is_granted('ROLE_SUPER_ADMIN')",
        ),
        new Get(
            uriTemplate: '/action-keys/{id}',
            security: "is_granted('ROLE_SUPER_ADMIN')",
        ),
    ],
    normalizationContext: ['groups' => ['action_key:read']],
)]
class ActionKey
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['action_key:read', 'role_action:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 150, unique: true)]
    #[Groups(['action_key:read', 'role_action:read'])]
    private string $key;

    #[ORM\Column(length: 200)]
    #[Groups(['action_key:read', 'role_action:read'])]
    private string $label;

    #[ORM\Column(length: 100)]
    #[Groups(['action_key:read', 'role_action:read'])]
    private string $module;

    /**
     * Field name on the subject entity to use for ownership check.
     * null = no ownership check needed for this action.
     * Values: 'chef_projet' | 'created_by'
     */
    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['action_key:read', 'role_action:read'])]
    private ?string $ownershipField = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): static
    {
        $this->key = $key;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getModule(): string
    {
        return $this->module;
    }

    public function setModule(string $module): static
    {
        $this->module = $module;

        return $this;
    }

    public function getOwnershipField(): ?string
    {
        return $this->ownershipField;
    }

    public function setOwnershipField(?string $ownershipField): static
    {
        $this->ownershipField = $ownershipField;

        return $this;
    }
}
