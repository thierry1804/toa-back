<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Entity;

use App\Domain\PermitTravail\Repository\PermitTravailGroupeRepository;
use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\User\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PermitTravailGroupeRepository::class)]
#[ORM\Table(name: '`permit_travail_groupe`')]
#[ORM\HasLifecycleCallbacks]
class PermitTravailGroupe
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['permit_travail_groupe:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 100)]
    #[Groups(['permit_travail_groupe:read'])]
    private ?string $codeSite = null;

    #[ORM\ManyToOne(targetEntity: PlanPrevention::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['permit_travail_groupe:read'])]
    private ?PlanPrevention $planPrevention = null;

    #[ORM\OneToOne(targetEntity: PermitTravail::class, inversedBy: 'groupeAsGeneral')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['permit_travail_groupe:read'])]
    private ?PermitTravail $permitGeneral = null;

    #[ORM\OneToOne(targetEntity: PermitTravail::class, inversedBy: 'groupeAsSpecialise')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['permit_travail_groupe:read'])]
    private ?PermitTravail $permitSpecialise = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['permit_travail_groupe:read'])]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['permit_travail_groupe:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getCodeSite(): ?string
    {
        return $this->codeSite;
    }

    public function setCodeSite(string $codeSite): static
    {
        $this->codeSite = $codeSite;

        return $this;
    }

    public function getPlanPrevention(): ?PlanPrevention
    {
        return $this->planPrevention;
    }

    public function setPlanPrevention(?PlanPrevention $planPrevention): static
    {
        $this->planPrevention = $planPrevention;

        return $this;
    }

    public function getPermitGeneral(): ?PermitTravail
    {
        return $this->permitGeneral;
    }

    public function setPermitGeneral(PermitTravail $permit): static
    {
        $this->permitGeneral = $permit;

        return $this;
    }

    public function getPermitSpecialise(): ?PermitTravail
    {
        return $this->permitSpecialise;
    }

    public function setPermitSpecialise(?PermitTravail $permit): static
    {
        $this->permitSpecialise = $permit;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): static
    {
        $this->createdBy = $createdBy;

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
