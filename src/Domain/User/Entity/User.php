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
use App\Api\Provider\PrestataireProvider;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use App\Domain\User\Validator\EntrepriseNameCoherence;
use App\Domain\User\Validator\UniqueEmail;

#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('USER_VIEW')"),
        new GetCollection(
            uriTemplate: '/prestataires',
            provider: PrestataireProvider::class,
            paginationEnabled: false,
        ),
        new Post(security: "is_granted('USER_CREATE')", processor: UserPasswordHasherProcessor::class, validationContext: ['groups' => ['Default', 'user:create']]),
        new Get(security: "is_granted('USER_VIEW', object)"),
        new Put(security: "is_granted('USER_EDIT', object)", processor: UserPasswordHasherProcessor::class),
        new Patch(security: "is_granted('USER_EDIT', object)", processor: UserPasswordHasherProcessor::class),
        new Delete(security: "is_granted('USER_DELETE', object)"),
    ],
    normalizationContext: ['groups' => ['user:read']],
    denormalizationContext: ['groups' => ['user:write']],
)]
#[ORM\Entity]
#[ORM\Table(name: '`user`')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEmail]
#[EntrepriseNameCoherence]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['user:read', 'plan_prevention:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    #[Groups(['user:read', 'user:write', 'plan_prevention:read'])]
    #[Assert\NotBlank(message: 'email_required')]
    #[Assert\Email(message: 'email_invalid')]
    private ?string $email = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['user:read', 'user:write', 'plan_prevention:read'])]
    #[Assert\NotBlank(message: 'name_required')]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['user:read', 'user:write', 'plan_prevention:read'])]
    private ?string $firstname = null;

    #[ORM\Column(type: 'string')]
    private ?string $password = null;

    #[Groups(['user:write'])]
    #[Assert\NotBlank(message: 'password_required', groups: ['user:create'])]
    private ?string $plainPassword = null;

    #[ORM\Column(type: 'json')]
    #[Groups(['user:read', 'user:write'])]
    #[Assert\NotBlank(message: 'role_required')]
    private array $roles = [];

    #[ORM\Column(type: 'boolean')]
    #[Groups(['user:read', 'user:write'])]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['user:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['user:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['user:read', 'user:write'])]
    private ?string $entrepriseName = null;

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

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getEntrepriseName(): ?string
    {
        return $this->entrepriseName;
    }

    public function setEntrepriseName(?string $entrepriseName): static
    {
        $this->entrepriseName = $entrepriseName;

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
