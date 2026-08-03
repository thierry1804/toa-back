<?php

declare(strict_types=1);

namespace App\Domain\ActivityPlanning\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'section_planifiee')]
#[ORM\Index(columns: ['planning_id', 'ordre'], name: 'idx_section_planning_ordre')]
#[ORM\HasLifecycleCallbacks]
class SectionPlanifiee
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['activity_planning:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: ActivityPlanning::class, inversedBy: 'sections')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ActivityPlanning $planning = null;

    #[ORM\Column(length: 150)]
    #[Groups(['activity_planning:read', 'activity_planning:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    private ?string $libelle = null;

    #[ORM\Column]
    #[Groups(['activity_planning:read', 'activity_planning:write'])]
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    private int $ordre = 0;

    #[ORM\OneToMany(
        targetEntity: TachePlanifiee::class,
        mappedBy: 'section',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    #[Groups(['activity_planning:read', 'activity_planning:write'])]
    private Collection $taches;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['activity_planning:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->taches = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getPlanning(): ?ActivityPlanning
    {
        return $this->planning;
    }

    public function setPlanning(?ActivityPlanning $planning): static
    {
        $this->planning = $planning;

        return $this;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getOrdre(): int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;

        return $this;
    }

    public function getTaches(): Collection
    {
        return $this->taches;
    }

    public function addTache(TachePlanifiee $tache): static
    {
        if (!$this->taches->contains($tache)) {
            $this->taches->add($tache);
            $tache->setSection($this);
        }

        return $this;
    }

    public function removeTache(TachePlanifiee $tache): static
    {
        $this->taches->removeElement($tache);

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
