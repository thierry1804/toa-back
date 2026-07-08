<?php

declare(strict_types=1);

namespace App\Domain\Role\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Domain\Role\Processor\RoleProcessor;
use App\Domain\Role\Repository\RoleRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RoleRepository::class)]
#[ORM\Table(name: '`role`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/roles',
            security: "is_granted('ROLE_SUPER_ADMIN')",
        ),
        new Post(
            uriTemplate: '/roles',
            security: "is_granted('ROLE_SUPER_ADMIN')",
            processor: RoleProcessor::class,
        ),
        new Get(
            uriTemplate: '/roles/{id}',
            security: "is_granted('ROLE_SUPER_ADMIN')",
        ),
        new Patch(
            uriTemplate: '/roles/{id}',
            security: "is_granted('ROLE_EDIT', object)",
            processor: RoleProcessor::class,
        ),
        new Delete(
            uriTemplate: '/roles/{id}',
            security: "is_granted('ROLE_DELETE', object)",
        ),
    ],
    normalizationContext: ['groups' => ['role:read']],
    denormalizationContext: ['groups' => ['role:write']],
)]
class Role
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['role:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Groups(['role:read', 'role:write'])]
    #[Assert\NotBlank(message: 'role_name_required')]
    #[Assert\Regex(pattern: '/^ROLE_[A-Z0-9_]+$/', message: 'role_name_invalid_format')]
    private string $name;

    #[ORM\Column(length: 150)]
    #[Groups(['role:read', 'role:write'])]
    #[Assert\NotBlank(message: 'role_label_required')]
    private string $label;

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['role:read', 'role:write'])]
    private ?string $description = null;

    #[ORM\Column]
    #[Groups(['role:read'])]
    private bool $isSystem = false;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['role:read'])]
    private ?\DateTimeImmutable $createdAt = null;

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
        $this->name = strtoupper($name);

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function isSystem(): bool
    {
        return $this->isSystem;
    }

    public function getIsSystem(): bool
    {
        return $this->isSystem;
    }

    public function setIsSystem(bool $isSystem): static
    {
        $this->isSystem = $isSystem;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if (null === $this->createdAt) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }
}
