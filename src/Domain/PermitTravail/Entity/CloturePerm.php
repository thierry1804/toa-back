<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Entity;

use App\Domain\PermitTravail\Enum\TypeCloturePerm;
use App\Domain\PermitTravail\Repository\CloturePermRepository;
use App\Domain\User\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: CloturePermRepository::class)]
#[ORM\Table(name: '`cloture_permit`')]
#[ORM\HasLifecycleCallbacks]
class CloturePerm
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: PermitTravail::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE', unique: true)]
    private ?PermitTravail $permitTravail = null;

    #[ORM\Column(length: 20, enumType: TypeCloturePerm::class)]
    private TypeCloturePerm $typeCloture = TypeCloturePerm::MANUELLE;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $dateClotureEffective = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column]
    private bool $accordClient = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $accordClientAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $clotureBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getPermitTravail(): ?PermitTravail
    {
        return $this->permitTravail;
    }

    public function setPermitTravail(?PermitTravail $permitTravail): static
    {
        $this->permitTravail = $permitTravail;

        return $this;
    }

    public function getTypeCloture(): TypeCloturePerm
    {
        return $this->typeCloture;
    }

    public function setTypeCloture(TypeCloturePerm $typeCloture): static
    {
        $this->typeCloture = $typeCloture;

        return $this;
    }

    public function getDateClotureEffective(): ?\DateTimeImmutable
    {
        return $this->dateClotureEffective;
    }

    public function setDateClotureEffective(\DateTimeImmutable $dateClotureEffective): static
    {
        $this->dateClotureEffective = $dateClotureEffective;

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function isAccordClient(): bool
    {
        return $this->accordClient;
    }

    public function setAccordClient(bool $accordClient): static
    {
        $this->accordClient = $accordClient;

        return $this;
    }

    public function getAccordClientAt(): ?\DateTimeImmutable
    {
        return $this->accordClientAt;
    }

    public function setAccordClientAt(?\DateTimeImmutable $accordClientAt): static
    {
        $this->accordClientAt = $accordClientAt;

        return $this;
    }

    public function getClotureBy(): ?User
    {
        return $this->clotureBy;
    }

    public function setClotureBy(?User $clotureBy): static
    {
        $this->clotureBy = $clotureBy;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if (null === $this->createdAt) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }
}
