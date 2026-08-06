<?php

declare(strict_types=1);

namespace App\Domain\Referentiel\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Api\Processor\InstallationEquipementSoftDeleteProcessor;
use App\Domain\Referentiel\Repository\InstallationEquipementRepository;
use App\Security\Voter\InstallationEquipementVoter;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InstallationEquipementRepository::class)]
#[ORM\Table(name: '`installation_equipement`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/referentiel/installations-equipements',
            security: "is_granted('INSTALLATION_EQUIPEMENT_VIEW')",
        ),
        new Post(
            uriTemplate: '/referentiel/installations-equipements',
            security: "is_granted('INSTALLATION_EQUIPEMENT_CREATE')",
        ),
        new Get(
            uriTemplate: '/referentiel/installations-equipements/{id}',
            security: "is_granted('INSTALLATION_EQUIPEMENT_VIEW')",
        ),
        new Patch(
            uriTemplate: '/referentiel/installations-equipements/{id}',
            security: "is_granted('INSTALLATION_EQUIPEMENT_EDIT', object)",
        ),
        new Delete(
            uriTemplate: '/referentiel/installations-equipements/{id}',
            security: "is_granted('INSTALLATION_EQUIPEMENT_DELETE', object)",
            processor: InstallationEquipementSoftDeleteProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['installation_equipement:read']],
    denormalizationContext: ['groups' => ['installation_equipement:write']],
)]
class InstallationEquipement
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['installation_equipement:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Groups(['installation_equipement:read', 'installation_equipement:write'])]
    #[Assert\NotBlank(message: 'nom_required')]
    #[Assert\Length(max: 255, maxMessage: 'nom_too_long')]
    private ?string $nom = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['installation_equipement:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['installation_equipement:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['installation_equipement:read'])]
    private ?\DateTimeImmutable $deletedAt = null;

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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function softDelete(): static
    {
        $this->deletedAt = new \DateTimeImmutable();

        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
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
