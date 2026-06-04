<?php

namespace App\Domain\User\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Api\Processor\UserPasswordHasherProcessor;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ApiResource(
    operations: [
        new GetCollection(),
        new Post(security: "is_granted('ROLE_SUPER_ADMIN')", processor: UserPasswordHasherProcessor::class, validationContext: ['groups' => ['Default', 'user:create']]),
        new Get(),
        new Put(security: "is_granted('ROLE_SUPER_ADMIN')", processor: UserPasswordHasherProcessor::class),
        new Patch(security: "is_granted('ROLE_SUPER_ADMIN')", processor: UserPasswordHasherProcessor::class),
        new Delete(security: "is_granted('ROLE_SUPER_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['user:read']],
    denormalizationContext: ['groups' => ['user:write']],
)]
#[ORM\Entity]
#[ORM\Table(name: '`user`')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'Email déjà utilisé')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['user:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    #[Groups(['user:read', 'user:write'])]
    #[Assert\NotBlank(message: 'L\'email est obligatoire')]
    #[Assert\Email(message: 'L\'email n\'est pas un email valide')]
    private ?string $email = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['user:read', 'user:write'])]
    #[Assert\NotBlank(message: 'Le nom est obligatoire')]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['user:read', 'user:write'])]
    private ?string $firstname = null;

    #[ORM\Column(type: 'string')]
    private ?string $password = null;

    #[Groups(['user:write'])]
    #[Assert\NotBlank(message: 'Le mot de passe est obligatoire', groups: ['user:create'])]
    private ?string $plainPassword = null;

    #[ORM\Column(type: 'json')]
    #[Groups(['user:read', 'user:write'])]
    #[Assert\NotBlank(message: 'Le rôle est obligatoire')]
    private array $roles = [];

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['user:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['user:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): static
    {
        $this->firstname = $firstname;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): self
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        $this->plainPassword = null;
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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
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
